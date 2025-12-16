<?php
/**
 * Search-First Interface
 *
 * Provides instant search across the knowledge base.
 * Searches through topic titles and content summaries.
 *
 * @package FranceRelocationAssistant
 * @version 3.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render the search interface HTML
 *
 * @return string HTML output
 */
function fra_render_search_interface() {
    ob_start();
    ?>
    <div class="fra-search-container">
        <div class="fra-search-wrapper">
            <span class="fra-search-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                    <circle cx="11" cy="11" r="8"/>
                    <path d="M21 21l-4.35-4.35"/>
                </svg>
            </span>
            <input
                type="text"
                id="fra-search-input"
                class="fra-search-input"
                placeholder="Search topics... (e.g., 'visa requirements', 'healthcare')"
                autocomplete="off"
                aria-label="Search knowledge base"
            />
            <button type="button" id="fra-search-clear" class="fra-search-clear" style="display: none;" aria-label="Clear search">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <div id="fra-search-results" class="fra-search-results" style="display: none;">
            <!-- Results populated by JavaScript -->
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Get searchable KB data for JavaScript
 *
 * Returns a simplified, searchable version of the KB for client-side search.
 *
 * @return array Searchable topics array
 */
function fra_get_searchable_kb() {
    $kb = get_option('fra_knowledge_base', array());
    $searchable = array();

    // Category metadata for display
    $category_labels = array(
        'visas' => array('icon' => '🛂', 'label' => 'Visas & Immigration'),
        'property' => array('icon' => '🏠', 'label' => 'Property Purchase'),
        'healthcare' => array('icon' => '🏥', 'label' => 'Healthcare & Retirement'),
        'taxes' => array('icon' => '📋', 'label' => 'Taxes & Law'),
        'driving' => array('icon' => '🚗', 'label' => 'Driving'),
        'shipping' => array('icon' => '📦', 'label' => 'Shipping & Pets'),
        'banking' => array('icon' => '🏦', 'label' => 'Banking'),
        'settling' => array('icon' => '🏡', 'label' => 'Settling In'),
    );

    foreach ($kb as $cat_key => $topics) {
        if (!is_array($topics)) continue;

        $cat_meta = isset($category_labels[$cat_key])
            ? $category_labels[$cat_key]
            : array('icon' => '📄', 'label' => ucfirst(str_replace('_', ' ', $cat_key)));

        foreach ($topics as $topic_key => $topic) {
            if (!isset($topic['title'])) continue;

            // Build searchable text from title and content
            $search_text = strtolower($topic['title']);

            // Add key points if available (first few for search context)
            if (!empty($topic['content']['key_points']) && is_array($topic['content']['key_points'])) {
                $points = array_slice($topic['content']['key_points'], 0, 3);
                $search_text .= ' ' . strtolower(implode(' ', $points));
            }

            // Add in_practice summary if available
            if (!empty($topic['content']['in_practice'])) {
                $search_text .= ' ' . strtolower(substr($topic['content']['in_practice'], 0, 200));
            }

            $searchable[] = array(
                'category' => $cat_key,
                'topic' => $topic_key,
                'title' => $topic['title'],
                'categoryIcon' => $cat_meta['icon'],
                'categoryLabel' => $cat_meta['label'],
                'searchText' => $search_text,
            );
        }
    }

    return $searchable;
}

/**
 * Enqueue search data as JavaScript variable
 *
 * Called on wp_enqueue_scripts to provide KB data to the search JS
 */
function fra_localize_search_data() {
    $searchable_kb = fra_get_searchable_kb();

    wp_localize_script('fra-search', 'fraSearchData', array(
        'topics' => $searchable_kb,
        'noResults' => __('No topics found. Try a different search term.', 'france-relocation-assistant'),
        'placeholder' => __('Search topics...', 'france-relocation-assistant'),
    ));
}
