            <label id="{{ $prefix }}identification_country_label" for="{{ $prefix }}identification_country">País emisor del documento</label>
            <div class="vita-country-select" x-data="VitaIdentification.countrySelect(() => form, '{{ $prefix }}')" x-id="['country-options']" @click.outside="close()" @keydown.escape.stop.prevent="close(true)" @focusout="if(!$el.contains($event.relatedTarget))close()" x-effect="if(disabled)close()">
                <input type="hidden" name="{{ $prefix }}identification_country" :value="value" :disabled="disabled">
                <button type="button" id="{{ $prefix }}identification_country" x-ref="countryTrigger" :disabled="disabled" :aria-expanded="opened" :aria-controls="$id('country-options')" aria-haspopup="listbox" aria-labelledby="{{ $prefix }}identification_country_label {{ $prefix }}identification_country_value" @click="opened ? close() : open()" @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)" class="vita-country-trigger">
                    <span id="{{ $prefix }}identification_country_value" x-text="label"></span>
                    <svg width="16" height="16" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m5 7.5 5 5 5-5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
                <div class="vita-country-dropdown" x-show="opened" x-cloak>
                    <div class="vita-country-search">
                        <input type="search" x-ref="countrySearch" x-model="query" @input="activeIndex=0" role="combobox" aria-label="Filtrar países" aria-autocomplete="list" :aria-expanded="opened" :aria-controls="$id('country-options')" :aria-activedescendant="options.length ? $id('country-options')+'-'+activeIndex : null" placeholder="Buscar país" autocomplete="off" @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)" @keydown.enter.prevent="chooseActive()" @keydown.tab="close()">
                    </div>
                    <div role="listbox" :id="$id('country-options')" aria-labelledby="{{ $prefix }}identification_country_label" x-ref="countryResults" class="vita-country-options">
                        <template x-for="(country,index) in options" :key="country[0]">
                            <button type="button" role="option" tabindex="-1" :id="$id('country-options')+'-'+index" :aria-selected="value===country[0]" :class="{'is-active':activeIndex===index,'is-selected':value===country[0]}" @mouseenter="activeIndex=index" @mousedown.prevent @click="choose(country[0])" x-text="country[1]"></button>
                        </template>
                    </div>
                    <p x-show="!options.length" role="status" class="p-3 text-sm text-gray-500">No se encontraron países.</p>
                </div>
            </div>
