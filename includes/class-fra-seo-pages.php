<?php
/**
 * France Relocation Assistant - SEO Virtual Pages
 *
 * Creates indexable, SEO-optimized pages from KB data without
 * duplicating content management. Each KB topic gets its own URL.
 *
 * Features:
 * - Clean URL structure: /guide/{category}/{topic}/
 * - Unique meta tags per page
 * - Schema.org Article/HowTo markup per topic
 * - Auto-generated XML sitemap
 * - Internal linking between related topics
 * - Breadcrumb support
 *
 * @package France_Relocation_Assistant
 * @since 3.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class FRA_SEO_Pages {

    /**
     * Base slug for guide pages
     */
    const BASE_SLUG = 'guide';

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Cached knowledge base data
     */
    private $kb_cache = null;

    /**
     * Category metadata for SEO
     */
    private static $category_meta = array(
        'visas' => array(
            'title' => 'Visas & Immigration',
            'description' => 'Complete guide to French visas for Americans. Learn about visitor visas, work visas, talent passports, and family visas.',
            'icon' => '🛂',
        ),
        'property' => array(
            'title' => 'Buying Property in France',
            'description' => 'Step-by-step guide for Americans buying property in France. Understand the process, costs, mortgages, and notaire fees.',
            'icon' => '🏠',
        ),
        'healthcare' => array(
            'title' => 'French Healthcare System',
            'description' => 'How French healthcare works for American expats. PUMA enrollment, Carte Vitale, mutuelle insurance explained.',
            'icon' => '🏥',
        ),
        'taxes' => array(
            'title' => 'Taxes & Legal',
            'description' => 'Tax obligations for Americans in France. US-France tax treaty, 183-day rule, FBAR, and FATCA requirements.',
            'icon' => '📋',
        ),
        'driving' => array(
            'title' => 'Driving in France',
            'description' => 'Driving license exchange for Americans, importing cars, French driving rules and requirements.',
            'icon' => '🚗',
        ),
        'shipping' => array(
            'title' => 'Shipping & Pets',
            'description' => 'Moving belongings to France from the USA. Shipping options, customs, and pet import requirements.',
            'icon' => '📦',
        ),
        'banking' => array(
            'title' => 'French Banking',
            'description' => 'Opening a French bank account as an American. FATCA compliance, recommended banks, and account types.',
            'icon' => '🏦',
        ),
        'settling' => array(
            'title' => 'Settling In France',
            'description' => 'Practical tips for settling into French life. Utilities, phone plans, internet, and daily life.',
            'icon' => '🏡',
        ),
    );

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Register rewrite rules
        add_action('init', array($this, 'register_rewrite_rules'), 10);

        // Add query vars
        add_filter('query_vars', array($this, 'add_query_vars'));

        // Template redirect for virtual pages
        add_action('template_redirect', array($this, 'handle_virtual_page'));

        // Add sitemap routes
        add_action('init', array($this, 'register_sitemap_route'));

        // Flush rewrite rules on activation
        register_activation_hook(FRA_PLUGIN_BASENAME, array($this, 'flush_rewrite_rules'));
    }

    /**
     * Get knowledge base data (with fallback to default file)
     *
     * @return array Knowledge base data
     */
    public function get_kb() {
        // Return cached if available
        if ($this->kb_cache !== null) {
            return $this->kb_cache;
        }

        // Try to get from main plugin instance first
        $fra = France_Relocation_Assistant::get_instance();
        if ($fra && method_exists($fra, 'get_knowledge_base')) {
            $this->kb_cache = $fra->get_knowledge_base();
            return $this->kb_cache;
        }

        // Fallback: try database
        $kb = get_option('fra_knowledge_base', array());

        // If empty, load from default file
        if (empty($kb)) {
            $default_file = FRA_PLUGIN_DIR . 'includes/knowledge-base-default.php';
            if (file_exists($default_file)) {
                $kb = include $default_file;
            }
        }

        $this->kb_cache = $kb;
        return $this->kb_cache;
    }

    /**
     * Register rewrite rules for guide pages
     */
    public function register_rewrite_rules() {
        // Category index: /guide/visas/
        add_rewrite_rule(
            '^' . self::BASE_SLUG . '/([^/]+)/?$',
            'index.php?fra_guide_category=$matches[1]',
            'top'
        );

        // Topic page: /guide/visas/visitor/
        add_rewrite_rule(
            '^' . self::BASE_SLUG . '/([^/]+)/([^/]+)/?$',
            'index.php?fra_guide_category=$matches[1]&fra_guide_topic=$matches[2]',
            'top'
        );

        // Guide index: /guide/
        add_rewrite_rule(
            '^' . self::BASE_SLUG . '/?$',
            'index.php?fra_guide_index=1',
            'top'
        );

        // XML Sitemap: /sitemap.xml
        add_rewrite_rule(
            '^sitemap\.xml$',
            'index.php?fra_sitemap=xml',
            'top'
        );

        // HTML Sitemap: /sitemap/
        add_rewrite_rule(
            '^sitemap/?$',
            'index.php?fra_sitemap=html',
            'top'
        );
    }

    /**
     * Register sitemap route
     */
    public function register_sitemap_route() {
        // Already handled in register_rewrite_rules
    }

    /**
     * Add custom query vars
     */
    public function add_query_vars($vars) {
        $vars[] = 'fra_guide_category';
        $vars[] = 'fra_guide_topic';
        $vars[] = 'fra_guide_index';
        $vars[] = 'fra_sitemap';
        return $vars;
    }

    /**
     * Flush rewrite rules
     */
    public function flush_rewrite_rules() {
        $this->register_rewrite_rules();
        flush_rewrite_rules();
    }

    /**
     * Handle virtual page requests
     */
    public function handle_virtual_page() {
        $category = get_query_var('fra_guide_category');
        $topic = get_query_var('fra_guide_topic');
        $guide_index = get_query_var('fra_guide_index');
        $sitemap = get_query_var('fra_sitemap');

        // Handle sitemap requests
        if ($sitemap === 'xml') {
            $this->render_xml_sitemap();
            exit;
        }

        if ($sitemap === 'html') {
            $this->render_html_sitemap();
            exit;
        }

        // Handle guide index
        if ($guide_index) {
            $this->render_guide_index();
            exit;
        }

        // Handle category or topic pages
        if ($category) {
            $kb = $this->get_kb();

            if (!isset($kb[$category])) {
                // Category not found - 404
                global $wp_query;
                $wp_query->set_404();
                status_header(404);
                return;
            }

            if ($topic) {
                // Render specific topic page
                if (!isset($kb[$category][$topic])) {
                    global $wp_query;
                    $wp_query->set_404();
                    status_header(404);
                    return;
                }
                $this->render_topic_page($category, $topic, $kb[$category][$topic]);
            } else {
                // Render category index page
                $this->render_category_page($category, $kb[$category]);
            }
            exit;
        }
    }

    /**
     * Render the main guide index page
     */
    private function render_guide_index() {
        $kb = $this->get_kb();

        $title = 'France Relocation Guide - Complete Resource for Americans Moving to France';
        $description = 'Comprehensive guide covering visas, property purchase, healthcare, taxes, driving, banking, and settling in France. Expert information for US citizens.';

        $this->render_page_header($title, $description, home_url('/guide/'));

        ?>
        <main class="fra-seo-page fra-guide-index">
            <div class="fra-seo-container">

                <header class="fra-seo-header">
                    <nav class="fra-seo-breadcrumb" aria-label="Breadcrumb">
                        <ol itemscope itemtype="https://schema.org/BreadcrumbList">
                            <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                                <a itemprop="item" href="<?php echo esc_url(home_url('/')); ?>">
                                    <span itemprop="name">Home</span>
                                </a>
                                <meta itemprop="position" content="1" />
                            </li>
                            <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                                <span itemprop="name">Guide</span>
                                <meta itemprop="position" content="2" />
                            </li>
                        </ol>
                    </nav>
                    <h1>France Relocation Guide</h1>
                    <p class="fra-seo-subtitle">Everything Americans need to know about moving to France</p>
                </header>

                <div class="fra-guide-categories">
                    <?php foreach ($kb as $cat_key => $topics) :
                        $meta = isset(self::$category_meta[$cat_key]) ? self::$category_meta[$cat_key] : array(
                            'title' => ucfirst(str_replace('_', ' ', $cat_key)),
                            'description' => '',
                            'icon' => '📄',
                        );
                        $topic_count = count($topics);
                    ?>
                    <a href="<?php echo esc_url(home_url('/guide/' . $cat_key . '/')); ?>" class="fra-guide-category-card">
                        <span class="fra-category-icon"><?php echo $meta['icon']; ?></span>
                        <h2><?php echo esc_html($meta['title']); ?></h2>
                        <p><?php echo esc_html($meta['description']); ?></p>
                        <span class="fra-topic-count"><?php echo $topic_count; ?> topics</span>
                    </a>
                    <?php endforeach; ?>
                </div>

                <?php $this->render_cta_section(); ?>

            </div>
        </main>
        <?php

        $this->render_page_footer();
    }

    /**
     * Render a category index page
     */
    private function render_category_page($category, $topics) {
        $meta = isset(self::$category_meta[$category]) ? self::$category_meta[$category] : array(
            'title' => ucfirst(str_replace('_', ' ', $category)),
            'description' => 'Guide to ' . $category . ' for Americans moving to France.',
            'icon' => '📄',
        );

        $title = $meta['title'] . ' - France Relocation Guide';
        $description = $meta['description'];
        $url = home_url('/guide/' . $category . '/');

        $this->render_page_header($title, $description, $url, $category);

        ?>
        <main class="fra-seo-page fra-category-page">
            <div class="fra-seo-container">

                <header class="fra-seo-header">
                    <nav class="fra-seo-breadcrumb" aria-label="Breadcrumb">
                        <ol itemscope itemtype="https://schema.org/BreadcrumbList">
                            <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                                <a itemprop="item" href="<?php echo esc_url(home_url('/')); ?>">
                                    <span itemprop="name">Home</span>
                                </a>
                                <meta itemprop="position" content="1" />
                            </li>
                            <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                                <a itemprop="item" href="<?php echo esc_url(home_url('/guide/')); ?>">
                                    <span itemprop="name">Guide</span>
                                </a>
                                <meta itemprop="position" content="2" />
                            </li>
                            <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                                <span itemprop="name"><?php echo esc_html($meta['title']); ?></span>
                                <meta itemprop="position" content="3" />
                            </li>
                        </ol>
                    </nav>
                    <h1><span class="fra-page-icon"><?php echo $meta['icon']; ?></span> <?php echo esc_html($meta['title']); ?></h1>
                    <p class="fra-seo-subtitle"><?php echo esc_html($meta['description']); ?></p>
                </header>

                <div class="fra-topic-list">
                    <?php foreach ($topics as $topic_key => $topic) :
                        $topic_title = isset($topic['title']) ? $topic['title'] : ucfirst($topic_key);
                        $topic_url = home_url('/guide/' . $category . '/' . $topic_key . '/');
                        $excerpt = $this->get_topic_excerpt($topic);
                    ?>
                    <article class="fra-topic-card">
                        <h2><a href="<?php echo esc_url($topic_url); ?>"><?php echo esc_html($topic_title); ?></a></h2>
                        <p><?php echo esc_html($excerpt); ?></p>
                        <a href="<?php echo esc_url($topic_url); ?>" class="fra-read-more">Read more →</a>
                    </article>
                    <?php endforeach; ?>
                </div>

                <?php $this->render_related_categories($category); ?>
                <?php $this->render_cta_section(); ?>

            </div>
        </main>
        <?php

        $this->render_page_footer();
    }

    /**
     * Render a specific topic page
     */
    private function render_topic_page($category, $topic_key, $topic) {
        $cat_meta = isset(self::$category_meta[$category]) ? self::$category_meta[$category] : array(
            'title' => ucfirst(str_replace('_', ' ', $category)),
            'icon' => '📄',
        );

        $topic_title = isset($topic['title']) ? $topic['title'] : ucfirst($topic_key);
        $title = $topic_title . ' - ' . $cat_meta['title'] . ' | France Relocation Guide';
        $description = $this->get_topic_excerpt($topic, 160);
        $url = home_url('/guide/' . $category . '/' . $topic_key . '/');

        $this->render_page_header($title, $description, $url, $category, $topic_key);

        // Get content
        $content = '';
        if (isset($topic['content'])) {
            if (is_array($topic['content'])) {
                $content = $this->format_structured_content($topic['content']);
            } else {
                $content = $topic['content'];
            }
        }

        // Get sources
        $sources = isset($topic['sources']) ? $topic['sources'] : array();

        // Get last updated
        $last_updated = isset($topic['last_updated']) ? $topic['last_updated'] : null;

        ?>
        <main class="fra-seo-page fra-topic-page">
            <div class="fra-seo-container">

                <header class="fra-seo-header">
                    <nav class="fra-seo-breadcrumb" aria-label="Breadcrumb">
                        <ol itemscope itemtype="https://schema.org/BreadcrumbList">
                            <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                                <a itemprop="item" href="<?php echo esc_url(home_url('/')); ?>">
                                    <span itemprop="name">Home</span>
                                </a>
                                <meta itemprop="position" content="1" />
                            </li>
                            <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                                <a itemprop="item" href="<?php echo esc_url(home_url('/guide/')); ?>">
                                    <span itemprop="name">Guide</span>
                                </a>
                                <meta itemprop="position" content="2" />
                            </li>
                            <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                                <a itemprop="item" href="<?php echo esc_url(home_url('/guide/' . $category . '/')); ?>">
                                    <span itemprop="name"><?php echo esc_html($cat_meta['title']); ?></span>
                                </a>
                                <meta itemprop="position" content="3" />
                            </li>
                            <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                                <span itemprop="name"><?php echo esc_html($topic_title); ?></span>
                                <meta itemprop="position" content="4" />
                            </li>
                        </ol>
                    </nav>
                    <h1><?php echo esc_html($topic_title); ?></h1>
                    <?php if ($last_updated) : ?>
                    <p class="fra-last-updated">Last updated: <?php echo esc_html(date('F j, Y', strtotime($last_updated))); ?></p>
                    <?php endif; ?>
                </header>

                <article class="fra-topic-content" itemscope itemtype="https://schema.org/Article">
                    <meta itemprop="headline" content="<?php echo esc_attr($topic_title); ?>">
                    <meta itemprop="author" content="Relo2France">
                    <?php if ($last_updated) : ?>
                    <meta itemprop="dateModified" content="<?php echo esc_attr($last_updated); ?>">
                    <?php endif; ?>

                    <div class="fra-content-body" itemprop="articleBody">
                        <?php echo $this->format_content_html($content); ?>
                    </div>

                    <?php if (!empty($sources)) : ?>
                    <aside class="fra-sources">
                        <h3>Official Sources</h3>
                        <ul>
                            <?php foreach ($sources as $source) : ?>
                            <li>
                                <a href="<?php echo esc_url($source['url']); ?>" target="_blank" rel="noopener">
                                    <?php echo esc_html($source['name']); ?>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </aside>
                    <?php endif; ?>
                </article>

                <?php $this->render_related_topics($category, $topic_key); ?>
                <?php $this->render_cta_section(); ?>

            </div>
        </main>
        <?php

        $this->render_page_footer();
    }

    /**
     * Render XML sitemap
     */
    private function render_xml_sitemap() {
        header('Content-Type: application/xml; charset=utf-8');

        $kb = $this->get_kb();
        $site_url = home_url();

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        // Homepage
        echo $this->sitemap_url($site_url . '/', 'daily', '1.0');

        // Guide index
        echo $this->sitemap_url($site_url . '/guide/', 'weekly', '0.9');

        // Category pages
        foreach ($kb as $cat_key => $topics) {
            echo $this->sitemap_url($site_url . '/guide/' . $cat_key . '/', 'weekly', '0.8');

            // Topic pages
            foreach ($topics as $topic_key => $topic) {
                $lastmod = isset($topic['last_updated']) ? $topic['last_updated'] : null;
                echo $this->sitemap_url(
                    $site_url . '/guide/' . $cat_key . '/' . $topic_key . '/',
                    'weekly',
                    '0.7',
                    $lastmod
                );
            }
        }

        // HTML sitemap page
        echo $this->sitemap_url($site_url . '/sitemap/', 'monthly', '0.3');

        echo '</urlset>';
    }

    /**
     * Generate a sitemap URL entry
     */
    private function sitemap_url($url, $changefreq = 'weekly', $priority = '0.5', $lastmod = null) {
        $output = "  <url>\n";
        $output .= "    <loc>" . esc_url($url) . "</loc>\n";
        if ($lastmod) {
            $output .= "    <lastmod>" . esc_html(date('Y-m-d', strtotime($lastmod))) . "</lastmod>\n";
        }
        $output .= "    <changefreq>" . esc_html($changefreq) . "</changefreq>\n";
        $output .= "    <priority>" . esc_html($priority) . "</priority>\n";
        $output .= "  </url>\n";
        return $output;
    }

    /**
     * Render HTML sitemap page
     */
    private function render_html_sitemap() {
        $kb = $this->get_kb();

        $title = 'Sitemap - France Relocation Guide';
        $description = 'Complete sitemap of all France relocation guide pages. Find information on visas, property, healthcare, taxes, and more.';

        $this->render_page_header($title, $description, home_url('/sitemap/'));

        ?>
        <main class="fra-seo-page fra-sitemap-page">
            <div class="fra-seo-container">

                <header class="fra-seo-header">
                    <h1>Sitemap</h1>
                    <p class="fra-seo-subtitle">All pages in our France relocation guide</p>
                </header>

                <div class="fra-sitemap-content">

                    <section class="fra-sitemap-section">
                        <h2>Main Pages</h2>
                        <ul>
                            <li><a href="<?php echo esc_url(home_url('/')); ?>">Home</a></li>
                            <li><a href="<?php echo esc_url(home_url('/guide/')); ?>">France Relocation Guide</a></li>
                        </ul>
                    </section>

                    <?php foreach ($kb as $cat_key => $topics) :
                        $meta = isset(self::$category_meta[$cat_key]) ? self::$category_meta[$cat_key] : array(
                            'title' => ucfirst(str_replace('_', ' ', $cat_key)),
                            'icon' => '📄',
                        );
                    ?>
                    <section class="fra-sitemap-section">
                        <h2>
                            <a href="<?php echo esc_url(home_url('/guide/' . $cat_key . '/')); ?>">
                                <?php echo $meta['icon']; ?> <?php echo esc_html($meta['title']); ?>
                            </a>
                        </h2>
                        <ul>
                            <?php foreach ($topics as $topic_key => $topic) :
                                $topic_title = isset($topic['title']) ? $topic['title'] : ucfirst($topic_key);
                            ?>
                            <li>
                                <a href="<?php echo esc_url(home_url('/guide/' . $cat_key . '/' . $topic_key . '/')); ?>">
                                    <?php echo esc_html($topic_title); ?>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                    <?php endforeach; ?>

                </div>

            </div>
        </main>
        <?php

        $this->render_page_footer();
    }

    /**
     * Render page header with proper meta tags
     */
    private function render_page_header($title, $description, $canonical_url, $category = null, $topic = null) {
        $site_name = get_bloginfo('name');

        // Build schema markup
        $schema = $this->build_page_schema($title, $description, $canonical_url, $category, $topic);

        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">

            <title><?php echo esc_html($title); ?></title>
            <meta name="description" content="<?php echo esc_attr($description); ?>">
            <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1">

            <link rel="canonical" href="<?php echo esc_url($canonical_url); ?>">

            <!-- Open Graph -->
            <meta property="og:locale" content="en_US">
            <meta property="og:type" content="article">
            <meta property="og:title" content="<?php echo esc_attr($title); ?>">
            <meta property="og:description" content="<?php echo esc_attr($description); ?>">
            <meta property="og:url" content="<?php echo esc_url($canonical_url); ?>">
            <meta property="og:site_name" content="<?php echo esc_attr($site_name); ?>">

            <!-- Twitter Card -->
            <meta name="twitter:card" content="summary_large_image">
            <meta name="twitter:title" content="<?php echo esc_attr($title); ?>">
            <meta name="twitter:description" content="<?php echo esc_attr($description); ?>">

            <!-- Schema.org JSON-LD -->
            <script type="application/ld+json">
            <?php echo wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); ?>
            </script>

            <?php wp_head(); ?>

            <link rel="stylesheet" href="<?php echo esc_url(FRA_PLUGIN_URL . 'assets/css/seo-pages.css'); ?>?ver=<?php echo FRA_VERSION; ?>">
        </head>
        <body <?php body_class('fra-seo-page-body'); ?>>
        <?php wp_body_open(); ?>
        <?php
    }

    /**
     * Render page footer
     */
    private function render_page_footer() {
        ?>
        <?php wp_footer(); ?>
        </body>
        </html>
        <?php
    }

    /**
     * Build schema.org markup for the page
     */
    private function build_page_schema($title, $description, $url, $category = null, $topic = null) {
        $site_url = home_url();
        $site_name = get_bloginfo('name');

        $schema = array(
            '@context' => 'https://schema.org',
            '@graph' => array(),
        );

        // Organization
        $schema['@graph'][] = array(
            '@type' => 'Organization',
            '@id' => $site_url . '/#organization',
            'name' => $site_name,
            'url' => $site_url,
        );

        // WebSite
        $schema['@graph'][] = array(
            '@type' => 'WebSite',
            '@id' => $site_url . '/#website',
            'url' => $site_url,
            'name' => $site_name,
            'publisher' => array('@id' => $site_url . '/#organization'),
        );

        // BreadcrumbList
        $breadcrumbs = array(
            array('name' => 'Home', 'url' => $site_url),
            array('name' => 'Guide', 'url' => $site_url . '/guide/'),
        );

        if ($category) {
            $cat_meta = isset(self::$category_meta[$category]) ? self::$category_meta[$category] : array('title' => ucfirst($category));
            $breadcrumbs[] = array('name' => $cat_meta['title'], 'url' => $site_url . '/guide/' . $category . '/');

            if ($topic) {
                $kb = $this->get_kb();
                $topic_title = isset($kb[$category][$topic]['title']) ? $kb[$category][$topic]['title'] : ucfirst($topic);
                $breadcrumbs[] = array('name' => $topic_title, 'url' => $url);
            }
        }

        $breadcrumb_schema = array(
            '@type' => 'BreadcrumbList',
            '@id' => $url . '#breadcrumb',
            'itemListElement' => array(),
        );

        foreach ($breadcrumbs as $i => $crumb) {
            $breadcrumb_schema['itemListElement'][] = array(
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $crumb['name'],
                'item' => $crumb['url'],
            );
        }

        $schema['@graph'][] = $breadcrumb_schema;

        // Article (for topic pages)
        if ($topic) {
            $schema['@graph'][] = array(
                '@type' => 'Article',
                '@id' => $url . '#article',
                'headline' => $title,
                'description' => $description,
                'url' => $url,
                'author' => array('@id' => $site_url . '/#organization'),
                'publisher' => array('@id' => $site_url . '/#organization'),
                'mainEntityOfPage' => array('@id' => $url),
                'isPartOf' => array('@id' => $site_url . '/#website'),
            );
        }

        return $schema;
    }

    /**
     * Get excerpt from topic content
     */
    private function get_topic_excerpt($topic, $length = 120) {
        $content = '';

        if (isset($topic['content'])) {
            if (is_array($topic['content'])) {
                // Structured content - use summary or first key point
                if (!empty($topic['content']['summary'])) {
                    $content = $topic['content']['summary'];
                } elseif (!empty($topic['content']['key_points']) && is_array($topic['content']['key_points'])) {
                    $content = $topic['content']['key_points'][0];
                }
            } else {
                $content = $topic['content'];
            }
        }

        // Strip markdown and HTML
        $content = strip_tags($content);
        $content = preg_replace('/\*\*([^*]+)\*\*/', '$1', $content); // Bold
        $content = preg_replace('/\*([^*]+)\*/', '$1', $content); // Italic
        $content = preg_replace('/\n+/', ' ', $content); // Newlines

        // Truncate
        if (strlen($content) > $length) {
            $content = substr($content, 0, $length);
            $content = substr($content, 0, strrpos($content, ' '));
            $content .= '...';
        }

        return $content;
    }

    /**
     * Format structured content array to readable text
     */
    private function format_structured_content($content) {
        $output = '';

        if (!empty($content['summary'])) {
            $output .= $content['summary'] . "\n\n";
        }

        if (!empty($content['key_points']) && is_array($content['key_points'])) {
            $output .= "**Key Points:**\n";
            foreach ($content['key_points'] as $point) {
                $output .= "• " . $point . "\n";
            }
            $output .= "\n";
        }

        if (!empty($content['details'])) {
            $output .= $content['details'] . "\n\n";
        }

        if (!empty($content['in_practice'])) {
            $output .= "**In Practice:**\n" . $content['in_practice'] . "\n";
        }

        return $output;
    }

    /**
     * Convert markdown-style content to HTML
     */
    private function format_content_html($content) {
        // Convert markdown bold to HTML
        $content = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $content);

        // Convert markdown italic to HTML
        $content = preg_replace('/\*([^*]+)\*/', '<em>$1</em>', $content);

        // Convert bullet points
        $content = preg_replace('/^• (.+)$/m', '<li>$1</li>', $content);
        $content = preg_replace('/(<li>.*<\/li>\n?)+/', '<ul>$0</ul>', $content);

        // Convert newlines to paragraphs
        $paragraphs = preg_split('/\n{2,}/', $content);
        $content = '';
        foreach ($paragraphs as $para) {
            $para = trim($para);
            if (!empty($para)) {
                // Don't wrap if already has block element
                if (preg_match('/^<(ul|ol|h[1-6]|div|table)/', $para)) {
                    $content .= $para . "\n";
                } else {
                    $content .= '<p>' . nl2br($para) . '</p>' . "\n";
                }
            }
        }

        return $content;
    }

    /**
     * Render related topics section
     */
    private function render_related_topics($current_category, $current_topic) {
        $kb = $this->get_kb();

        if (!isset($kb[$current_category])) return;

        $related = array();
        foreach ($kb[$current_category] as $topic_key => $topic) {
            if ($topic_key !== $current_topic) {
                $related[$topic_key] = $topic;
            }
        }

        if (empty($related)) return;

        // Limit to 4 related topics
        $related = array_slice($related, 0, 4, true);

        ?>
        <aside class="fra-related-topics">
            <h3>Related Topics</h3>
            <div class="fra-related-grid">
                <?php foreach ($related as $topic_key => $topic) :
                    $topic_title = isset($topic['title']) ? $topic['title'] : ucfirst($topic_key);
                    $topic_url = home_url('/guide/' . $current_category . '/' . $topic_key . '/');
                ?>
                <a href="<?php echo esc_url($topic_url); ?>" class="fra-related-card">
                    <?php echo esc_html($topic_title); ?> →
                </a>
                <?php endforeach; ?>
            </div>
        </aside>
        <?php
    }

    /**
     * Render related categories section
     */
    private function render_related_categories($current_category) {
        $kb = $this->get_kb();

        $related = array();
        foreach (array_keys($kb) as $cat_key) {
            if ($cat_key !== $current_category) {
                $related[] = $cat_key;
            }
        }

        if (empty($related)) return;

        // Limit to 4
        $related = array_slice($related, 0, 4);

        ?>
        <aside class="fra-related-categories">
            <h3>Explore Other Topics</h3>
            <div class="fra-related-grid">
                <?php foreach ($related as $cat_key) :
                    $meta = isset(self::$category_meta[$cat_key]) ? self::$category_meta[$cat_key] : array(
                        'title' => ucfirst($cat_key),
                        'icon' => '📄',
                    );
                ?>
                <a href="<?php echo esc_url(home_url('/guide/' . $cat_key . '/')); ?>" class="fra-related-card">
                    <?php echo $meta['icon']; ?> <?php echo esc_html($meta['title']); ?> →
                </a>
                <?php endforeach; ?>
            </div>
        </aside>
        <?php
    }

    /**
     * Render CTA section
     */
    private function render_cta_section() {
        ?>
        <section class="fra-cta-section">
            <h3>Need Personalized Guidance?</h3>
            <p>Use our AI-powered assistant for answers tailored to your specific situation.</p>
            <a href="<?php echo esc_url(home_url('/')); ?>" class="fra-cta-button">
                Ask the Assistant →
            </a>
        </section>
        <?php
    }

    /**
     * Get all guide URLs for external use (e.g., other sitemaps)
     */
    public static function get_all_urls() {
        $urls = array();
        $site_url = home_url();

        // Get KB via instance
        $instance = self::get_instance();
        $kb = $instance->get_kb();

        $urls[] = $site_url . '/guide/';

        foreach ($kb as $cat_key => $topics) {
            $urls[] = $site_url . '/guide/' . $cat_key . '/';

            foreach ($topics as $topic_key => $topic) {
                $urls[] = $site_url . '/guide/' . $cat_key . '/' . $topic_key . '/';
            }
        }

        return $urls;
    }
}

// Initialize
FRA_SEO_Pages::get_instance();
