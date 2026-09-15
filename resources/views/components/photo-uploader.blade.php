@props(['hint' => 'Scatta una foto o scegli dalla galleria'])
{{--
    Uploader foto con DUE pulsanti espliciti, per avere lo stesso
    comportamento su iPhone e Android:
      • "Scatta foto"  → apre direttamente la fotocamera (capture);
      • "Galleria"     → apre la galleria/file (selezione anche multipla).
    Le foto vengono compresse lato client e accumulate nel campo "carrier"
    (name="foto[]"). Il JavaScript (public/js/app.js) gestisce più input
    "data-picker" nello stesso uploader.
--}}
<div class="uploader" data-uploader>
    <div class="uploader-actions">
        <label class="uploader-btn">📷 Scatta foto
            <input type="file" accept="image/*" capture="environment" data-picker hidden>
        </label>
        <label class="uploader-btn">🖼️ Galleria
            <input type="file" accept="image/*" multiple data-picker hidden>
        </label>
    </div>
    <div class="hint">{{ $hint }}</div>
    <div class="thumbs" data-thumbs></div>
    <input type="file" name="foto[]" multiple data-carrier hidden>
</div>
