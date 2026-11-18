'use strict';
window.LocalInvoice={async read(file,progress,signal){
 const base='/vendor/ocr/';let worker,pdf,loading;
 const abort=()=>{worker?.terminate();loading?.destroy();};signal?.addEventListener('abort',abort,{once:true});
 const check=()=>{if(signal?.aborted)throw new Error('Lectura cancelada.');};
 async function ocr(canvas){check();if(!worker)worker=await Tesseract.createWorker('spa',1,{workerPath:base+'worker.min.js',corePath:base+'core',langPath:base+'lang',workerBlobURL:false,logger:m=>{if(m.status==='recognizing text')progress('Reconociendo texto: '+Math.round(m.progress*100)+' %');}});check();const r=await worker.recognize(canvas);check();return r.data.text;}
 try{
  if(file.size>5*1024*1024)throw Error('Máximo 5 MB por factura.');
  let text='';
  if(file.type==='application/pdf'||/\.pdf$/i.test(file.name)){
   progress('Abriendo PDF en este navegador…');const lib=await import('/vendor/ocr/pdf/pdf.mjs');lib.GlobalWorkerOptions.workerSrc=base+'pdf/pdf.worker.mjs';
   loading=lib.getDocument({data:new Uint8Array(await file.arrayBuffer()),isEvalSupported:false,cMapUrl:base+'pdf/cmaps/',cMapPacked:true,standardFontDataUrl:base+'pdf/standard_fonts/',wasmUrl:base+'pdf/wasm/'});pdf=await loading.promise;
   if(pdf.numPages>10)throw Error('Esta prueba admite hasta 10 páginas por factura. Divide el PDF antes de leerlo.');
   for(let n=1;n<=pdf.numPages;n++){check();progress(`Leyendo página ${n} de ${pdf.numPages}…`);const page=await pdf.getPage(n),content=await page.getTextContent();let pageText='',lastY=null;
    for(const item of content.items){if(!('str' in item))continue;const y=item.transform[5];if(lastY!==null&&Math.abs(y-lastY)>3)pageText+='\n';pageText+=item.str+(item.hasEOL?'\n':' ');lastY=y;}
    if(pageText.replace(/\s/g,'').length<30){const original=page.getViewport({scale:1}),viewport=page.getViewport({scale:Math.min(2.2,2500/Math.max(original.width,original.height))});const canvas=document.createElement('canvas');canvas.width=Math.ceil(viewport.width);canvas.height=Math.ceil(viewport.height);await page.render({canvasContext:canvas.getContext('2d'),viewport}).promise;pageText=await ocr(canvas);canvas.width=canvas.height=0;}
    text+=pageText+'\n';page.cleanup();if(text.length>150000)throw Error('Demasiado texto para esta prueba. Divide el documento.');
   }
  }else{
   if(!['image/jpeg','image/png'].includes(file.type))throw Error('Selecciona un PDF, JPG o PNG.');progress('Preparando imagen…');const bitmap=await createImageBitmap(file);try{if(bitmap.width*bitmap.height>25000000)throw Error('La imagen es demasiado grande; reduce su resolución.');const scale=Math.min(1,2500/Math.max(bitmap.width,bitmap.height)),canvas=document.createElement('canvas');canvas.width=Math.ceil(bitmap.width*scale);canvas.height=Math.ceil(bitmap.height*scale);const ctx=canvas.getContext('2d');ctx.fillStyle='#fff';ctx.fillRect(0,0,canvas.width,canvas.height);ctx.drawImage(bitmap,0,0,canvas.width,canvas.height);text=await ocr(canvas);canvas.width=canvas.height=0;}finally{bitmap.close();}
  }
  check();return {...InvoiceFields.parse(text),text};
 }finally{signal?.removeEventListener('abort',abort);if(worker)await worker.terminate().catch(()=>{});if(pdf)await pdf.destroy();}
}};
