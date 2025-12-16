/**
 * Search-First Interface JavaScript
 *
 * Provides instant client-side search across the knowledge base.
 * Filters topics as the user types with debouncing for performance.
 *
 * @package FranceRelocationAssistant
 * @version 3.1.0
 */

(function() {
    'use strict';

    // Wait for DOM ready
    document.addEventListener('DOMContentLoaded', function() {
        initSearch();
    });

    /**
     * Initialize the search functionality
     */
    function initSearch() {
        const searchInput = document.getElementById('fra-search-input');
        const searchResults = document.getElementById('fra-search-results');
        const clearButton = document.getElementById('fra-search-clear');

        if (!searchInput || !searchResults) {
            return;
        }

        // Check if search data is available
        if (typeof fraSearchData === 'undefined' || !fraSearchData.topics) {
            console.warn('FRA Search: No search data available');
            return;
        }

        let debounceTimer = null;
        const DEBOUNCE_DELAY = 150; // ms

        /**
         * Handle search input
         */
        searchInput.addEventListener('input', function() {
            const query = this.value.trim();

            // Show/hide clear button
            if (clearButton) {
                clearButton.style.display = query.length > 0 ? 'flex' : 'none';
            }

            // Debounce search
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function() {
                performSearch(query);
            }, DEBOUNCE_DELAY);
        });

        /**
         * Handle clear button click
         */
        if (clearButton) {
            clearButton.addEventListener('click', function() {
                searchInput.value = '';
                searchResults.style.display = 'none';
                searchResults.innerHTML = '';
                clearButton.style.display = 'none';
                searchInput.focus();
            });
        }

        /**
         * Handle keyboard navigation
         */
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                this.value = '';
                searchResults.style.display = 'none';
                searchResults.innerHTML = '';
                if (clearButton) clearButton.style.display = 'none';
            } else if (e.key === 'ArrowDown') {
                e.preventDefault();
                const firstResult = searchResults.querySelector('.fra-search-result-item');
                if (firstResult) firstResult.focus();
            }
        });

        /**
         * Perform the search
         */
        function performSearch(query) {
            if (query.length < 2) {
                searchResults.style.display = 'none';
                searchResults.innerHTML = '';
                return;
            }

            const queryLower = query.toLowerCase();
            const queryTerms = queryLower.split(/\s+/).filter(function(t) { return t.length > 0; });

            // Score and filter results
            const scored = fraSearchData.topics.map(function(topic) {
                let score = 0;
                const title = topic.title.toLowerCase();
                const searchText = topic.searchText;

                // Exact title match = highest score
                if (title === queryLower) {
                    score += 100;
                }
                // Title starts with query
                else if (title.startsWith(queryLower)) {
                    score += 50;
                }
                // Title contains full query
                else if (title.includes(queryLower)) {
                    score += 30;
                }

                // Score individual terms
                queryTerms.forEach(function(term) {
                    if (title.includes(term)) {
                        score += 10;
                    }
                    if (searchText.includes(term)) {
                        score += 5;
                    }
                });

                return {
                    topic: topic,
                    score: score
                };
            });

            // Filter to matches and sort by score
            const results = scored
                .filter(function(item) { return item.score > 0; })
                .sort(function(a, b) { return b.score - a.score; })
                .slice(0, 8) // Limit to top 8 results
                .map(function(item) { return item.topic; });

            renderResults(results, query);
        }

        /**
         * Render search results
         */
        function renderResults(results, query) {
            if (results.length === 0) {
                searchResults.innerHTML = '<div class="fra-search-no-results">' +
                    fraSearchData.noResults + '</div>';
                searchResults.style.display = 'block';
                return;
            }

            let html = '';
            results.forEach(function(topic, index) {
                // Highlight matching text in title
                const highlightedTitle = highlightText(topic.title, query);

                html += '<button class="fra-search-result-item" ' +
                    'data-category="' + escapeHtml(topic.category) + '" ' +
                    'data-topic="' + escapeHtml(topic.topic) + '" ' +
                    'tabindex="0">' +
                    '<span class="fra-search-result-icon">' + topic.categoryIcon + '</span>' +
                    '<span class="fra-search-result-content">' +
                        '<span class="fra-search-result-title">' + highlightedTitle + '</span>' +
                        '<span class="fra-search-result-category">' + escapeHtml(topic.categoryLabel) + '</span>' +
                    '</span>' +
                    '<span class="fra-search-result-arrow">→</span>' +
                '</button>';
            });

            searchResults.innerHTML = html;
            searchResults.style.display = 'block';

            // Add click handlers to results
            const resultItems = searchResults.querySelectorAll('.fra-search-result-item');
            resultItems.forEach(function(item, index) {
                item.addEventListener('click', function() {
                    selectResult(this);
                });

                // Keyboard navigation
                item.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        selectResult(this);
                    } else if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        const next = resultItems[index + 1];
                        if (next) next.focus();
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        if (index === 0) {
                            searchInput.focus();
                        } else {
                            resultItems[index - 1].focus();
                        }
                    } else if (e.key === 'Escape') {
                        searchInput.value = '';
                        searchResults.style.display = 'none';
                        searchInput.focus();
                    }
                });
            });
        }

        /**
         * Select a search result and navigate to that topic
         */
        function selectResult(item) {
            const category = item.dataset.category;
            const topic = item.dataset.topic;

            // Clear search
            searchInput.value = '';
            searchResults.style.display = 'none';
            if (clearButton) clearButton.style.display = 'none';

            // Trigger the topic selection using existing FRA functionality
            // Look for existing subtopic button and click it
            const subtopicBtn = document.querySelector(
                '.fra-subtopic-btn[data-category="' + category + '"][data-topic="' + topic + '"]'
            );

            if (subtopicBtn) {
                // First expand the category if collapsed
                const categoryHeader = document.querySelector(
                    '.fra-category-header[data-category="' + category + '"]'
                );
                if (categoryHeader) {
                    const subtopicsContainer = document.querySelector(
                        '.fra-subtopics[data-category="' + category + '"]'
                    );
                    if (subtopicsContainer && !subtopicsContainer.classList.contains('fra-open')) {
                        categoryHeader.click();
                    }
                }

                // Small delay to allow expansion animation, then click topic
                setTimeout(function() {
                    subtopicBtn.click();
                }, 100);
            } else {
                // Fallback: try to trigger topic via custom event
                const event = new CustomEvent('fra-select-topic', {
                    detail: { category: category, topic: topic }
                });
                document.dispatchEvent(event);
            }
        }

        /**
         * Highlight matching text
         */
        function highlightText(text, query) {
            if (!query) return escapeHtml(text);

            const escaped = escapeHtml(text);
            const queryEscaped = escapeRegExp(query);

            try {
                const regex = new RegExp('(' + queryEscaped + ')', 'gi');
                return escaped.replace(regex, '<mark>$1</mark>');
            } catch (e) {
                return escaped;
            }
        }

        /**
         * Escape HTML special characters
         */
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        /**
         * Escape special regex characters
         */
        function escapeRegExp(string) {
            return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        }

        /**
         * Close search results when clicking outside
         */
        document.addEventListener('click', function(e) {
            const searchContainer = document.querySelector('.fra-search-container');
            if (searchContainer && !searchContainer.contains(e.target)) {
                searchResults.style.display = 'none';
            }
        });
    }
})();
