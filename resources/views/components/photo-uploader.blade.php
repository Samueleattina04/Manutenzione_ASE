@props(['hint' => 'Puoi scattare una foto o sceglierla dalla galleria'])
{{--
    Uploader foto con un unico pulsante: su smartphone/tablet apre il menu
    nativo che permette di SCATTARE una foto con la fotocamera OPPURE di
    sceglierla dalla galleria/file.
    Nota: l'input NON usa "multiple" né "capture", perché "multiple" su
    Android nasconde l'opzione fotocamera. Si aggiunge una foto alla volta
    (ritoccando il pulsante) e le foto vengono accumulate via JavaScript nel
    campo "carrier" (name="foto[]") dopo la compressione lato client.
--}}
<div class="uploader" data-uploader>
    <div class="uploader-actions">
        <label class="uploader-btn">📷 Aggiungi foto
            <input type="file" accept="image/*" data-picker hidden>
        </label>
    </div>
    <div class="hint">{{ $hint }}</div>
    <div class="thumbs" data-thumbs></div>
    <input type="file" name="foto[]" multiple data-carrier hidden>
</div>
