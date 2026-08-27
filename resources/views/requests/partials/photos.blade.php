@if($photos->count())
    <div class="photo-grid">
        @foreach($photos as $a)
            <a href="{{ $a->url() }}" data-lightbox>
                <img src="{{ $a->url() }}" alt="{{ $a->original_name }}" loading="lazy">
            </a>
        @endforeach
    </div>
@else
    <div class="muted" style="font-size:13px">Nessuna foto.</div>
@endif
