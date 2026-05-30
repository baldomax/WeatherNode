@extends('layouts.admin')

@section('title', __('Footer'))

@php
    // Clean up any empty links from database on page load
    $currentLinks = \App\Models\Setting::getValue('footer.custom_links', []);
    if (is_array($currentLinks) && !empty($currentLinks)) {
        $cleanedLinks = [];
        foreach ($currentLinks as $link) {
            if (is_array($link) && !empty($link['label']) && !empty($link['url']) && trim($link['label']) !== '' && trim($link['url']) !== '') {
                $cleanedLinks[] = $link;
            }
        }
        // If we removed empty links, save the cleaned version
        if (count($cleanedLinks) !== count($currentLinks)) {
            \App\Models\Setting::setValue('footer.custom_links', $cleanedLinks, 'json', 'footer');
        }
    }
@endphp

@section('content')
<div class="w-full" x-data="footerManager()" x-init="init()">
    <!-- Breadcrumb -->
    <nav class="mb-6 text-sm">
        <ol class="flex items-center space-x-2">
            <li><a href="{{ route('admin.settings.index') }}" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">{{ __('Settings') }}</a></li>
            <li><svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></li>
            <li class="text-gray-900 dark:text-white font-medium">{{ __('Footer') }}</li>
        </ol>
    </nav>

    <!-- Header -->
    <div class="mb-8">
        <div class="flex items-center space-x-4">
            <div class="p-3 rounded-xl bg-slate-100 dark:bg-slate-900/30">
                <svg class="w-8 h-8 text-slate-600 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Footer') }}</h1>
                <p class="text-gray-500 dark:text-gray-400">{{ __('Configure footer links and content') }}</p>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-6 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl">
            <div class="flex items-center">
                <svg class="w-5 h-5 text-green-600 dark:text-green-400 mr-3" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <p class="text-green-800 dark:text-green-200">{{ session('success') }}</p>
            </div>
        </div>
    @endif

    <form action="{{ route('admin.settings.update', 'footer') }}" method="POST" id="footerForm">
        @csrf
        
        <!-- Footer Enabled Toggle -->
        <div class="mb-6 bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5">
            <div class="flex items-center justify-between">
                <div>
                    <label class="block text-sm font-medium text-gray-900 dark:text-white">{{ __('Enable Personal Footer Sections') }}</label>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ __('Show or hide personal footer sections. WeatherNode links, disclaimer, and license will always remain visible.') }}</p>
                </div>
                <x-toggle-switch
                    :enabled="\App\Models\Setting::getValue('footer.enabled', true)"
                    name="footer_enabled"
                    :labelEnabled="__('Enabled')"
                    :labelDisabled="__('Disabled')"
                />
            </div>
        </div>

        <!-- Info Box -->
        <div class="mb-6 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl p-4">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                </svg>
                <div class="text-sm text-blue-800 dark:text-blue-200">
                    <p class="font-medium mb-1">{{ __('Important') }}</p>
                    <p>{{ __('WeatherNode project links, disclaimer, license, and copyright notice will always remain visible regardless of these settings. Only personal sections (station info, custom links, social media) can be hidden.') }}</p>
                </div>
            </div>
        </div>

        <!-- Section Visibility -->
        <div class="mb-6 bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">{{ __('Personal Section Visibility') }}</h2>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">{{ __('Control which personal sections appear in the footer. WeatherNode links are always visible.') }}</p>
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <label class="block text-sm font-medium text-gray-900 dark:text-white">{{ __('Station Information') }}</label>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Show station hardware and location details') }}</p>
                    </div>
                    <x-toggle-switch
                        :enabled="\App\Models\Setting::getValue('footer.show_station_info', true)"
                        name="footer_show_station_info"
                    />
                </div>
                <div class="flex items-center justify-between">
                    <div>
                        <label class="block text-sm font-medium text-gray-900 dark:text-white">{{ __('Coordinates') }}</label>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Show or hide station coordinates in the footer') }}</p>
                    </div>
                    <x-toggle-switch
                        :enabled="\App\Models\Setting::getValue('footer.show_coordinates', true)"
                        name="footer_show_coordinates"
                    />
                </div>
                <div class="flex items-center justify-between">
                    <div>
                        <label class="block text-sm font-medium text-gray-900 dark:text-white">{{ __('Social Media') }}</label>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Show social media links from contact settings') }}</p>
                    </div>
                    <x-toggle-switch
                        :enabled="\App\Models\Setting::getValue('footer.show_social', true)"
                        name="footer_show_social"
                    />
                </div>
                <div class="flex items-center justify-between">
                    <div>
                        <label class="block text-sm font-medium text-gray-900 dark:text-white">{{ __('Quick Links') }}</label>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Show custom quick links section') }}</p>
                    </div>
                    <x-toggle-switch
                        :enabled="\App\Models\Setting::getValue('footer.show_quick_links', true)"
                        name="footer_show_quick_links"
                    />
                </div>
                <div class="flex items-center justify-between">
                    <div>
                        <label class="block text-sm font-medium text-gray-900 dark:text-white">{{ __('Legal Links') }}</label>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Show privacy policy, terms, etc.') }}</p>
                    </div>
                    <x-toggle-switch
                        :enabled="\App\Models\Setting::getValue('footer.show_legal', true)"
                        name="footer_show_legal"
                    />
                </div>
                <div class="flex items-center justify-between">
                    <div>
                        <label class="block text-sm font-medium text-gray-900 dark:text-white">{{ __('Show SEO text in footer') }}</label>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Show optional text in the fat footer for search engines to scrape (location, services, keywords).') }}</p>
                    </div>
                    <x-toggle-switch
                        :enabled="\App\Models\Setting::getValue('footer.show_seo_text', false)"
                        name="footer_show_seo_text"
                    />
                </div>
            </div>
        </div>

        <!-- Custom Links -->
        <div class="mb-6 bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Custom Links') }}</h2>
                <div class="flex gap-2">
                    <button type="button" @click="links = []" class="px-3 py-2 bg-gray-600 hover:bg-gray-700 dark:bg-gray-500 dark:hover:bg-gray-600 text-white text-sm font-medium rounded-lg transition" x-show="links.length > 0">
                        {{ __('Clear All') }}
                    </button>
                    <button type="button" @click="addLink()" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600 text-white text-sm font-medium rounded-lg transition">
                        {{ __('Add Link') }}
                    </button>
                </div>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">{{ __('Add custom links to appear in the Quick Links section. Use translation keys (e.g., "footer.link.about") for multi-language support, or plain text.') }}</p>
            
            <div class="space-y-3" id="linksContainer" x-data="{ sortable: null }" x-init="
                if (typeof Sortable !== 'undefined') {
                    sortable = new Sortable($el, {
                        handle: '.drag-handle',
                        animation: 150,
                        onEnd: function(evt) {
                            const item = links.splice(evt.oldIndex, 1)[0];
                            links.splice(evt.newIndex, 0, item);
                        }
                    });
                }
            ">
                <template x-for="(link, index) in links" :key="index">
                    <div class="flex items-start gap-3 p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg border border-gray-200 dark:border-gray-600 hover:border-gray-300 dark:hover:border-gray-500 transition-colors" x-show="link">
                        <div class="flex-shrink-0 pt-2 text-gray-400 cursor-grab active:cursor-grabbing drag-handle" title="{{ __('Drag to reorder') }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"/>
                            </svg>
                        </div>
                        <div class="flex-1 grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Label / Translation Key') }}</label>
                                <input type="text" 
                                       x-model="link.label" 
                                       :name="'link_label_' + index"
                                       placeholder="{{ __('footer.link.about or About') }}"
                                       class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500"
                                       required>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Use a translation key (e.g., footer.link.about) for multi-language support') }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('URL') }}</label>
                                <input type="text" 
                                       x-model="link.url" 
                                       :name="'link_url_' + index"
                                       placeholder="{{ __('https://example.com or /about') }}"
                                       class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500"
                                       required>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Full URL or relative path') }}</p>
                            </div>
                        </div>
                        <button type="button" @click="removeLink(index)" class="flex-shrink-0 p-2 text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 transition-colors" title="{{ __('Remove link') }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </div>
                </template>
                <div x-show="links.length === 0" class="text-center py-8 text-gray-500 dark:text-gray-400 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg">
                    <svg class="w-12 h-12 mx-auto mb-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                    </svg>
                    <p class="font-medium">{{ __('No custom links added yet') }}</p>
                    <p class="text-sm mt-1">{{ __('Click "Add Link" to get started') }}</p>
                </div>
            </div>
            <input type="hidden" name="footer_custom_links" :value="JSON.stringify(getValidLinks())">
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-between">
            <a href="{{ route('admin.settings.index') }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">
                ← {{ __('Back to Settings') }}
            </a>
            <button type="submit" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600 text-white font-medium rounded-lg transition shadow-sm">
                {{ __('Save Changes') }}
            </button>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
function footerManager() {
    @php
        // Get links and filter out any empty ones
        $rawLinks = \App\Models\Setting::getValue('footer.custom_links', []);
        $validLinks = [];
        if (is_array($rawLinks)) {
            foreach ($rawLinks as $link) {
                if (is_array($link) && !empty($link['label']) && !empty($link['url'])) {
                    $validLinks[] = $link;
                }
            }
        }
    @endphp
    
    return {
        links: @json($validLinks),
        
        init() {
            // Ensure links is always an array
            if (!Array.isArray(this.links)) {
                this.links = [];
            }
            // Filter out any empty links that might have snuck in
            this.links = this.links.filter(link => link && (link.label || link.url));
        },
        
        addLink() {
            this.links.push({ label: '', url: '' });
            // Scroll to the new link after a brief delay
            this.$nextTick(() => {
                const container = document.getElementById('linksContainer');
                if (container) {
                    const lastLink = container.lastElementChild;
                    if (lastLink && lastLink.classList.contains('rounded-lg')) {
                        lastLink.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                        // Focus on the label input
                        const labelInput = lastLink.querySelector('input[type="text"]');
                        if (labelInput) {
                            setTimeout(() => labelInput.focus(), 100);
                        }
                    }
                }
            });
        },
        
        removeLink(index) {
            if (index >= 0 && index < this.links.length) {
                if (confirm('{{ __('Are you sure you want to remove this link?') }}')) {
                    this.links.splice(index, 1);
                }
            }
        },
        
        getValidLinks() {
            return this.links.filter(link => link && link.label && link.url);
        }
    }
}
</script>
@endsection
