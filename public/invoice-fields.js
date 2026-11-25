'use strict';
// Conservative Spanish invoice hints. Unknown/ambiguous fields remain blank for review.
(function(root){
 function money(value){
  let s=value.replace(/\s/g,'');
  if(!/^-?\d[\d.,]*$/.test(s)||s.startsWith('-'))return '';
  if(s.includes(','))s=s.replace(/\./g,'').replace(',','.');
  else if(/^\d{1,3}(\.\d{3})+$/.test(s))s=s.replace(/\./g,'');
  return /^\d+(\.\d{1,2})?$/.test(s)&&Number.isFinite(Number(s))?Number(s).toFixed(2):'';
 }
 function date(s){const m=s.match(/\b(?:(\d{4})[-/.](\d{1,2})[-/.](\d{1,2})|(\d{1,2})[-/.](\d{1,2})[-/.](\d{4}))\b/);if(!m)return '';const y=+(m[1]||m[6]),mo=+(m[2]||m[5]),d=+(m[3]||m[4]);const v=new Date(Date.UTC(y,mo-1,d));return y>=1900&&v.getUTCFullYear()===y&&v.getUTCMonth()===mo-1&&v.getUTCDate()===d?`${y}-${String(mo).padStart(2,'0')}-${String(d).padStart(2,'0')}`:'';}
 function parse(text){
  const lines=text.split(/\r?\n/).map(s=>s.trim()).filter(Boolean),norm=s=>s.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase();
  const f={party:'',date:'',due_date:'',reference:'',base:'',tax:'',invoice_total:'',currency:''},warnings=[];
  function unique(values,label){const v=[...new Set(values.filter(Boolean))];if(v.length>1)warnings.push(`Hay varios valores posibles para ${label}; compruébalo en el original.`);return v.length===1?v[0]:'';}
  function dates(pattern){return lines.flatMap((l,i)=>pattern.test(norm(l))?[date(l)||date(lines[i+1]||'')]:[]);}
  function amounts(pattern){return lines.flatMap((l,i)=>{if(!pattern.test(norm(l)))return [];const after=l.replace(pattern,'');const tokens=after.match(/-?\d[\d.,]*(?:\s*[€]|(?=\s|$))/g)||[];const a=tokens.map(t=>money(t.replace('€','').trim())).filter(Boolean);return a.length?[a[a.length-1]]:[];});}
  f.date=unique(dates(/fecha\s*(?:de\s*)?(?:factura|compra|emision|expedicion)|^fecha\s*:/),'fecha');
  f.due_date=unique(dates(/vencimiento|fecha\s*(?:limite|de pago)/),'vencimiento');
  f.base=unique(amounts(/base\s*imponible|subtotal/i),'base imponible');
  f.tax=unique(amounts(/(?:total\s*)?\biva\b|cuota\s*(?:de\s*)?iva/i),'IVA');
  f.invoice_total=unique(amounts(/^(?:importe\s*)?total(?:\s*(?:factura|a pagar))?\s*[:\s]/i),'total');
  const suppliers=lines.flatMap(l=>{const m=l.match(/^(?:proveedor|emisor|raz[oó]n social)\s*[:\-]\s*(.+)$/i);return m?[m[1]]:[];});
  f.party=unique(suppliers,'proveedor').slice(0,190);
  if(!f.party){const candidate=lines.slice(0,4).find(l=>/\bS\.?\s*(?:L|A)\.?(?:U\.?)?\s*$/i.test(l)&&!/(cliente|destinatario)/i.test(l));if(candidate){f.party=candidate.slice(0,190);warnings.push('El proveedor se ha propuesto a partir de la cabecera; confirma que no sea el cliente.');}}
  f.reference=unique(lines.flatMap(l=>{const m=l.match(/^(?:factura\s*(?:n[ºo°.]?|numero|n[uú]mero|#)|n[ºo°.]?\s*(?:de\s*)?factura)\s*[:\-]?\s*([A-Z0-9][A-Z0-9/_.-]*)\s*$/i);return m?[m[1]]:[];}),'número').slice(0,100);
  const currencies=[...new Set((text.match(/\b(?:EUR|USD|GBP|CHF)\b|€/g)||[]).map(c=>c==='€'?'EUR':c))];f.currency=currencies.length===1?currencies[0]:'';
  if(currencies.some(c=>c!=='EUR'))warnings.push('Se detecta otra moneda: convierte y revisa los importes antes de registrarlos en EUR.');
  if(f.base&&f.tax&&f.invoice_total&&Math.abs(Number(f.base)+Number(f.tax)-Number(f.invoice_total))>.011)warnings.push('El total no coincide con base + IVA; puede haber retenciones, descuentos u otros conceptos.');
  warnings.push('Lectura orientativa: revisa proveedor, fechas, base, IVA y total. Un IVA vacío no significa exento.');
  return {fields:f,warnings};
 }
 const api={parse,money,date};if(typeof module==='object'&&module.exports)module.exports=api;else root.InvoiceFields=api;
})(globalThis);
