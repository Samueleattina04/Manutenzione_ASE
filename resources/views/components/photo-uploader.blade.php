@props(['hint' => 'Scatta una foto o scegli dalla galleria'])
{{--
    Uploader foto con due scelte esplicite:
    - "Scatta foto": apre direttamente la fotocamera (capture) su smartphone/tablet
    - "Dalla galleria": apre la galleria / i file
    I due input servono solo a scegliere le immagini; quello vero che viene
    inviato è il "carrier" (name="foto[]"), popolato via JavaScript dopo la
    compressione lato client.
--}}
<div class="uploader" data-uploader>
    <div class="uploader-actions">
        <label class="uploader-btn">📷 Scatta foto
            <input type="file" accept="image/*" capture="environment" data-picker hidden>
        </label>
        <label class="uploader-btn">🖼️ Dalla galleria
            <input type="file" accept="image/*" multiple data-picker hidden>
        </label>
    </div>
    <div class="hint">{{ $hint }}</div>
    <div class="thumbs" data-thumbs></div>
    <input type="file" name="foto[]" multiple data-carrier hidden>
</div>
