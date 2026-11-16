"""Build same-origin OCR assets from pinned official npm archives. No install scripts run."""
import base64, hashlib, io, json, tarfile, urllib.request
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
OUT=ROOT/'public/vendor/ocr'
lock=json.loads((ROOT/'tools/ocr-assets.lock.json').read_text())
for name,p in lock.items():
    raw=urllib.request.urlopen(p['url'],timeout=120).read()
    algorithm,expected=p['integrity'].split('-',1)
    assert base64.b64encode(hashlib.new(algorithm,raw).digest()).decode()==expected, 'Integrity mismatch: '+name
    with tarfile.open(fileobj=io.BytesIO(raw),mode='r:gz') as archive:
        for item in archive.getmembers():
            relative=item.name.removeprefix('package/')
            target=None
            if name=='tesseract.js' and relative in ['dist/tesseract.min.js','dist/worker.min.js','dist/tesseract.min.js.LICENSE.txt','dist/worker.min.js.LICENSE.txt']:
                target=OUT/Path(relative).name
            if name=='tesseract.js-core' and relative.endswith('.wasm.js') and '/' not in relative:
                target=OUT/'core'/relative
            if name=='pdfjs-dist' and (relative in ['build/pdf.mjs','build/pdf.worker.mjs'] or relative.startswith(('cmaps/','standard_fonts/','wasm/'))):
                target=OUT/'pdf'/relative.removeprefix('build/')
            if name=='@tesseract.js-data/spa' and relative=='4.0.0_best_int/spa.traineddata.gz':
                target=OUT/'lang/spa.traineddata.gz'
            if relative.upper() in ['LICENSE','LICENSE.TXT','NOTICE']:
                target=OUT/'licenses'/(name.replace('/','-')+'-'+relative)
            if target and item.isfile():
                target.parent.mkdir(parents=True,exist_ok=True)
                target.write_bytes(archive.extractfile(item).read())
print('Local OCR assets ready:',OUT)
