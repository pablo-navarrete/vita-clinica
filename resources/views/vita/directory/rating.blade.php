<div class="directory-rating">
    @if($card['review_count'])
        <span class="directory-rating-stars" aria-hidden="true">@for($star=1;$star<=5;$star++)<i class="{{ $star <= round((float)$card['rating']) ? 'fa-solid' : 'fa-regular' }} fa-star"></i>@endfor</span>
        <span><strong>{{ number_format((float)$card['rating'],1,',','.') }}</strong> / 5 · {{ $card['review_count'] }} {{ $card['review_count']===1 ? 'opinión' : 'opiniones' }}</span>
    @else
        <span class="directory-rating-stars" aria-hidden="true">☆☆☆☆☆</span><span>Sin opiniones todavía</span>
    @endif
</div>
