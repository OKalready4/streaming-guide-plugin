<?php
/**
 * OpenAI API Handler Class - FIXED VERSION
 * 
 * Handles all content generation using OpenAI API with UNIQUE content generation
 * Prevents repetitive articles by using advanced variation techniques
 */

if (!defined('ABSPATH')) {
    exit;
}

class Upcoming_Movies_OpenAI_API {
    private $api_key;
    private $api_url = 'https://api.openai.com/v1/chat/completions';

    public function __construct() {
        $this->api_key = get_option('upcoming_movies_openai_api_key');
    }

    public function is_configured() {
        return !empty($this->api_key);
    }

    /**
     * Generate content with OpenAI
     */
    public function generate_content($messages, $temperature = 0.7) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', 'OpenAI API key not configured');
        }

        $args = array(
            'method' => 'POST',
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'model' => 'gpt-4o',
                'messages' => $messages,
                'temperature' => max(0.0, min(2.0, floatval($temperature))),
                'max_tokens' => 4000
            )),
            'timeout' => 60
        );

        $response = wp_remote_post($this->api_url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_error', 'Failed to parse OpenAI response');
        }

        if (isset($data['error'])) {
            return new WP_Error('openai_api_error', $data['error']['message']);
        }

        if (isset($data['choices'][0]['message']['content'])) {
            return $data['choices'][0]['message']['content'];
        }

        return new WP_Error('unexpected_response', 'Unexpected OpenAI response');
    }

    /**
     * FIXED: Generate truly unique articles with advanced variation
     */
    public function generate_enhanced_article($content_data) {
        $is_tv_show = $content_data['is_tv_show'] ?? false;
        $title = $content_data['title'] ?? ($content_data['name'] ?? 'Untitled');
        $overview = $content_data['overview'] ?? '';
        $release_date = $content_data['release_date'] ?? ($content_data['first_air_date'] ?? '');
        $year = !empty($release_date) ? date('Y', strtotime($release_date)) : date('Y');
        $genres = isset($content_data['genres']) ? array_column($content_data['genres'], 'name') : array();
        $genre_text = !empty($genres) ? implode(', ', array_slice($genres, 0, 3)) : 'Drama';
        
        // FIXED: Advanced variation system
        $variation_data = $this->generate_variation_parameters($content_data);
        
        $content_type_name = $is_tv_show ? 'TV series' : 'movie';
        
        // FIXED: Dynamic prompt with unique angles
        $prompt = $this->build_unique_prompt($content_data, $variation_data);
        
        $messages = array(
            array(
                'role' => 'system',
                'content' => $this->get_varied_system_prompt($variation_data)
            ),
            array(
                'role' => 'user',
                'content' => $prompt
            )
        );
        
        // FIXED: Higher temperature for more variation
        return $this->generate_content($messages, $variation_data['temperature']);
    }

    /**
     * FIXED: Generate advanced variation parameters for unique content
     */
    private function generate_variation_parameters($content_data) {
        $is_tv_show = $content_data['is_tv_show'] ?? false;
        $genres = isset($content_data['genres']) ? array_column($content_data['genres'], 'name') : array();
        $release_date = $content_data['release_date'] ?? ($content_data['first_air_date'] ?? '');
        $year = !empty($release_date) ? date('Y', strtotime($release_date)) : date('Y');
        
        // Create unique seed based on content
        $title = $content_data['title'] ?? ($content_data['name'] ?? 'default');
        $seed = crc32($title . $year . implode('', $genres)) + time();
        
        // FIXED: Multiple writing angles to choose from
        $writing_angles = array(
            'entertainment_focus' => 'Focus on entertainment value and viewer experience',
            'critical_analysis' => 'Provide in-depth critical analysis and artistic merit',
            'cultural_impact' => 'Examine cultural significance and social themes',
            'technical_appreciation' => 'Highlight technical achievements and craftsmanship',
            'audience_perspective' => 'Write from the perspective of different audience segments',
            'genre_evolution' => 'Discuss how this content evolves or challenges genre conventions',
            'streaming_context' => 'Focus on the streaming era and binge-watching culture',
            'comparative_analysis' => 'Compare with similar content and industry trends'
        );
        
        // FIXED: Multiple article structures
        $article_structures = array(
            'classic_review' => array(
                'sections' => array('Plot Overview', 'Performance Analysis', 'Technical Excellence', 'Final Verdict'),
                'style' => 'traditional review format'
            ),
            'deep_dive' => array(
                'sections' => array('What Makes It Special', 'Behind the Scenes', 'Character Development', 'Why It Matters'),
                'style' => 'analytical deep-dive'
            ),
            'viewer_guide' => array(
                'sections' => array('What to Expect', 'Who Should Watch', 'Viewing Experience', 'Bottom Line'),
                'style' => 'practical viewing guide'
            ),
            'cultural_lens' => array(
                'sections' => array('Cultural Context', 'Thematic Elements', 'Social Commentary', 'Lasting Impact'),
                'style' => 'cultural analysis'
            ),
            'entertainment_spotlight' => array(
                'sections' => array('The Hook', 'What Works', 'Standout Moments', 'The Verdict'),
                'style' => 'entertainment-focused review'
            ),
            'streaming_perspective' => array(
                'sections' => array('Binge Factor', 'Platform Perfect', 'Audience Appeal', 'Streaming Verdict'),
                'style' => 'streaming-centric analysis'
            )
        );
        
        // FIXED: Multiple title templates for variety
        $title_templates = $this->get_dynamic_title_templates($content_data);
        
        // FIXED: Writing tones for personality
        $writing_tones = array(
            'enthusiastic' => 'Write with enthusiasm and excitement',
            'analytical' => 'Use a thoughtful, analytical tone',
            'conversational' => 'Write in a friendly, conversational style',
            'professional' => 'Maintain a professional, authoritative tone',
            'passionate' => 'Show genuine passion for the medium',
            'accessible' => 'Write in an accessible, inclusive manner'
        );
        
        // Select variations based on seed
        $angle_keys = array_keys($writing_angles);
        $structure_keys = array_keys($article_structures);
        $tone_keys = array_keys($writing_tones);
        
        $selected_angle = $angle_keys[$seed % count($angle_keys)];
        $selected_structure = $structure_keys[($seed + 1) % count($structure_keys)];
        $selected_tone = $tone_keys[($seed + 2) % count($tone_keys)];
        $selected_title = $title_templates[($seed + 3) % count($title_templates)];
        
        return array(
            'writing_angle' => $writing_angles[$selected_angle],
            'article_structure' => $article_structures[$selected_structure],
            'writing_tone' => $writing_tones[$selected_tone],
            'title_template' => $selected_title,
            'temperature' => 0.85 + (($seed % 20) / 100), // 0.85-1.04 range
            'focus_area' => $this->determine_focus_area($genres, $is_tv_show),
            'unique_angle' => $selected_angle,
            'seed' => $seed
        );
    }

    /**
     * FIXED: Get dynamic title templates with high variety
     */
    private function get_dynamic_title_templates($content_data) {
        $title = $content_data['title'] ?? ($content_data['name'] ?? 'Unknown Title');
        $release_date = $content_data['release_date'] ?? ($content_data['first_air_date'] ?? '');
        $year = !empty($release_date) ? date('Y', strtotime($release_date)) : date('Y');
        $genres = isset($content_data['genres']) ? array_column($content_data['genres'], 'name') : array();
        $is_tv_show = $content_data['is_tv_show'] ?? false;
        
        $templates = array();
        
        // Entertainment-focused templates
        $templates[] = "{$title}: The " . ($is_tv_show ? "Series" : "Film") . " That's Redefining Entertainment";
        $templates[] = "Why {$title} Is the " . ($is_tv_show ? "Binge-Worthy Experience" : "Cinematic Journey") . " You Need";
        $templates[] = "{$title} Delivers " . ($is_tv_show ? "Season After Season of" : "") . " Quality Storytelling";
        $templates[] = "Streaming Gold: {$title} Proves Why " . ($is_tv_show ? "Series" : "Movies") . " Keep Getting Better";
        
        // Critical analysis templates
        $templates[] = "Inside {$title}: A Deep Dive Into {$year}'s Most Compelling " . ($is_tv_show ? "Series" : "Film");
        $templates[] = "{$title} Analysis: Breaking Down What Makes This " . ($is_tv_show ? "Show" : "Movie") . " Special";
        $templates[] = "The Art of {$title}: Examining " . ($is_tv_show ? "Television" : "Cinema") . " at Its Finest";
        $templates[] = "{$title} Explored: Why Critics and Audiences Can't Stop Talking About It";
        
        // Cultural impact templates
        $templates[] = "{$title}: More Than Entertainment - A Cultural Phenomenon";
        $templates[] = "How {$title} Is Changing the Conversation About " . ($is_tv_show ? "Television" : "Film");
        $templates[] = "{$title} and the Future of " . ($is_tv_show ? "Streaming Television" : "Modern Cinema");
        $templates[] = "Beyond the Screen: {$title}'s Impact on Popular Culture";
        
        // Viewer-focused templates
        $templates[] = "Your Complete Guide to {$title}: Everything You Need to Know";
        $templates[] = "{$title} Review: Is This " . ($is_tv_show ? "Series" : "Film") . " Worth Your Time?";
        $templates[] = "Should You Watch {$title}? A Honest Assessment";
        $templates[] = "{$title}: The " . ($is_tv_show ? "Show" : "Movie") . " Experience You've Been Waiting For";
        
        // Genre-specific templates
        if ($this->has_genre($genres, ['Action', 'Adventure'])) {
            $templates[] = "{$title}: Action-Packed " . ($is_tv_show ? "Series" : "Thrills") . " That Deliver";
            $templates[] = "Adrenaline Rush: {$title} Brings High-Octane Entertainment";
        }
        
        if ($this->has_genre($genres, ['Comedy'])) {
            $templates[] = "{$title}: The Comedy " . ($is_tv_show ? "Series" : "Hit") . " That Gets Everything Right";
            $templates[] = "Laugh Out Loud: Why {$title} Is This Year's Funniest " . ($is_tv_show ? "Show" : "Film");
        }
        
        if ($this->has_genre($genres, ['Drama', 'Romance'])) {
            $templates[] = "{$title}: Emotional Storytelling at Its Peak";
            $templates[] = "Heart and Soul: {$title} Captures the Human Experience";
        }
        
        if ($this->has_genre($genres, ['Horror', 'Thriller'])) {
            $templates[] = "{$title}: The " . ($is_tv_show ? "Series" : "Film") . " That Will Keep You on Edge";
            $templates[] = "Spine-Chilling Excellence: {$title} Masters the Art of Suspense";
        }
        
        // Streaming-era templates
        $templates[] = "{$title}: Perfect " . ($is_tv_show ? "Binge-Watching" : "Streaming") . " for Any Night";
        $templates[] = "Stream Now: {$title} Is the Content You've Been Searching For";
        $templates[] = "{$title} Review: Quality " . ($is_tv_show ? "Television" : "Cinema") . " in the Digital Age";
        
        return $templates;
    }

    /**
     * FIXED: Build unique prompts with advanced variation
     */
    private function build_unique_prompt($content_data, $variation_data) {
        $is_tv_show = $content_data['is_tv_show'] ?? false;
        $title = $content_data['title'] ?? ($content_data['name'] ?? 'Untitled');
        $overview = $content_data['overview'] ?? '';
        $release_date = $content_data['release_date'] ?? ($content_data['first_air_date'] ?? '');
        $year = !empty($release_date) ? date('Y', strtotime($release_date)) : date('Y');
        $genres = isset($content_data['genres']) ? array_column($content_data['genres'], 'name') : array();
        $genre_text = !empty($genres) ? implode(', ', array_slice($genres, 0, 3)) : 'Drama';
        
        $content_type = $is_tv_show ? 'TV series' : 'movie';
        $sections = implode(', ', $variation_data['article_structure']['sections']);
        
        $prompt = "Write a comprehensive {$content_type} article about '{$title}' ({$year}) with a unique perspective.\n\n";
        
        // FIXED: Include the title template
        $prompt .= "CRITICAL: Use this exact title: {$variation_data['title_template']}\n\n";
        
        // FIXED: Add unique angle instruction
        $prompt .= "WRITING APPROACH: {$variation_data['writing_angle']}\n";
        $prompt .= "ARTICLE STYLE: {$variation_data['article_structure']['style']}\n";
        $prompt .= "TONE: {$variation_data['writing_tone']}\n";
        $prompt .= "FOCUS AREA: {$variation_data['focus_area']}\n\n";
        
        $prompt .= "Content Details:\n";
        $prompt .= "- Title: {$title}\n";
        $prompt .= "- Release Year: {$year}\n";
        $prompt .= "- Type: " . ($is_tv_show ? 'TV Series' : 'Movie') . "\n";
        if (!empty($genres)) {
            $prompt .= "- Genres: {$genre_text}\n";
        }
        if (!empty($overview)) {
            $prompt .= "- Synopsis: {$overview}\n";
        }
        
        $prompt .= "\nSTRUCTURAL REQUIREMENTS:\n";
        $prompt .= "1. Use WordPress Gutenberg blocks format with <!--wp:heading--> and <!--wp:paragraph--> tags\n";
        $prompt .= "2. Start with the specified title as H2\n";
        $prompt .= "3. Write 900-1200 words with engaging, varied content\n";
        $prompt .= "4. Use these sections as H3 headings: {$sections}\n";
        $prompt .= "5. Each section should have a unique perspective and avoid repetitive phrasing\n";
        $prompt .= "6. NO MARKDOWN - Use clean HTML only\n";
        $prompt .= "7. Each paragraph should be 4-6 sentences with varied sentence structure\n";
        $prompt .= "8. DO NOT use generic phrases like 'masterpiece', 'must-see', or 'cinematic experience'\n";
        $prompt .= "9. Avoid repetitive transitions between paragraphs\n";
        $prompt .= "10. Make each article feel completely different from others\n\n";
        
        // FIXED: Add specific variation instructions
        $prompt .= "UNIQUENESS REQUIREMENTS:\n";
        $prompt .= "- Use varied vocabulary and avoid common film/TV review clichés\n";
        $prompt .= "- Each section should approach the content from a different angle\n";
        $prompt .= "- Include specific, concrete observations rather than generic praise\n";
        $prompt .= "- Vary paragraph lengths and structures throughout\n";
        $prompt .= "- Use unique transitional phrases and section connections\n";
        $prompt .= "- Focus on specific elements that make this content distinctive\n\n";
        
        $prompt .= "FORMAT EXAMPLE:\n";
        $prompt .= "<!-- wp:heading -->\n";
        $prompt .= "<h2 class=\"wp-block-heading\">[EXACT TITLE FROM ABOVE]</h2>\n";
        $prompt .= "<!-- /wp:heading -->\n\n";
        $prompt .= "<!-- wp:paragraph -->\n";
        $prompt .= "<p>Unique opening content here...</p>\n";
        $prompt .= "<!-- /wp:paragraph -->\n";
        
        return $prompt;
    }

    /**
     * FIXED: Get varied system prompts
     */
    private function get_varied_system_prompt($variation_data) {
        $system_prompts = array(
            'entertainment_focus' => "You are an entertainment journalist who focuses on what makes content genuinely enjoyable and engaging for viewers.",
            'critical_analysis' => "You are a film/TV critic who provides thoughtful analysis of artistic and technical elements.",
            'cultural_impact' => "You are a cultural commentator who examines how entertainment reflects and influences society.",
            'technical_appreciation' => "You are a industry insider who appreciates the craft and technical excellence behind great content.",
            'audience_perspective' => "You are a viewer advocate who helps audiences understand if content is right for them.",
            'genre_evolution' => "You are a genre specialist who understands how content fits into and evolves entertainment categories.",
            'streaming_context' => "You are a streaming culture expert who understands modern viewing habits and platform dynamics.",
            'comparative_analysis' => "You are an industry analyst who places content in context with current trends and similar works."
        );
        
        $base_prompt = $system_prompts[$variation_data['unique_angle']] ?? $system_prompts['entertainment_focus'];
        
        $base_prompt .= " Write engaging, varied articles that avoid repetitive language and generic film/TV review phrases. ";
        $base_prompt .= "Focus on creating unique content that feels fresh and distinctive. Use specific observations rather than generic praise. ";
        $base_prompt .= "Vary your sentence structure, paragraph lengths, and transitional phrases to create truly unique articles.";
        
        return $base_prompt;
    }

    /**
     * FIXED: Determine focus area based on genre and type
     */
    private function determine_focus_area($genres, $is_tv_show) {
        if ($this->has_genre($genres, ['Action', 'Adventure'])) {
            return $is_tv_show ? 'Action sequences and character development across episodes' : 'Choreography, stunts, and pacing';
        } elseif ($this->has_genre($genres, ['Comedy'])) {
            return $is_tv_show ? 'Comedic timing and character chemistry in series format' : 'Humor style and comedic performances';
        } elseif ($this->has_genre($genres, ['Drama', 'Romance'])) {
            return $is_tv_show ? 'Character arcs and emotional storytelling across seasons' : 'Performance depth and emotional impact';
        } elseif ($this->has_genre($genres, ['Horror', 'Thriller'])) {
            return $is_tv_show ? 'Suspense building and atmosphere in episodic format' : 'Tension, scares, and psychological elements';
        } elseif ($this->has_genre($genres, ['Science Fiction', 'Fantasy'])) {
            return $is_tv_show ? 'World-building and concept development over time' : 'Visual effects and conceptual execution';
        } elseif ($this->has_genre($genres, ['Animation', 'Family'])) {
            return $is_tv_show ? 'Animation quality and family appeal across episodes' : 'Animation artistry and all-ages entertainment';
        } else {
            return $is_tv_show ? 'Series structure and character development' : 'Storytelling and cinematic elements';
        }
    }

    /**
     * Check if content has specific genres
     */
    private function has_genre($genres, $target_genres) {
        if (empty($genres) || empty($target_genres)) {
            return false;
        }
        
        foreach ($target_genres as $target) {
            foreach ($genres as $genre) {
                if (stripos($genre, $target) !== false) {
                    return true;
                }
            }
        }
        
        return false;
    }

    /**
     * Generate simple article for basic cases
     */
    public function generate_simple_article($title, $overview, $year, $is_tv_show = false) {
        $content_type = $is_tv_show ? 'series' : 'movie';
        $messages = array(
            array(
                'role' => 'system',
                'content' => "You are a professional entertainment writer. Create engaging, SEO-friendly articles about movies and TV shows."
            ),
            array(
                'role' => 'user',
                'content' => "Write a 500-word article about the {$content_type} '{$title}' ({$year}). Synopsis: {$overview}. Use WordPress block format with proper heading and paragraph tags."
            )
        );
        
        return $this->generate_content($messages, 0.7);
    }

    /**
     * Generate fallback article when AI fails
     */
    private function generate_fallback_article($content_data) {
        $is_tv_show = $content_data['is_tv_show'] ?? false;
        $title = $is_tv_show ? ($content_data['name'] ?? 'Untitled') : ($content_data['title'] ?? 'Untitled');
        $overview = $content_data['overview'] ?? '';
        $release_date = $is_tv_show ? ($content_data['first_air_date'] ?? '') : ($content_data['release_date'] ?? '');
        $year = !empty($release_date) ? date('Y', strtotime($release_date)) : date('Y');
        
        $content_type = $is_tv_show ? 'series' : 'film';
        
        $content = "<!-- wp:heading -->\n";
        $content .= "<h2 class=\"wp-block-heading\">{$title} ({$year}): Complete Viewing Guide</h2>\n";
        $content .= "<!-- /wp:heading -->\n\n";
        
        if (!empty($overview)) {
            $content .= "<!-- wp:paragraph -->\n";
            $content .= "<p>{$overview}</p>\n";
            $content .= "<!-- /wp:paragraph -->\n\n";
        }
        
        $content .= "<!-- wp:heading {\"level\":3} -->\n";
        $content .= "<h3 class=\"wp-block-heading\">About This " . ucfirst($content_type) . "</h3>\n";
        $content .= "<!-- /wp:heading -->\n\n";
        
        $content .= "<!-- wp:paragraph -->\n";
        $content .= "<p>{$title} is a compelling {$content_type} that offers viewers an engaging entertainment experience. ";
        
        if ($is_tv_show) {
            $content .= "This series brings together talented performers and creative storytelling to deliver quality television content.";
        } else {
            $content .= "This film showcases the artistry and creativity that makes modern cinema so captivating.";
        }
        
        $content .= "</p>\n";
        $content .= "<!-- /wp:paragraph -->\n\n";
        
        $content .= "<!-- wp:heading {\"level\":3} -->\n";
        $content .= "<h3 class=\"wp-block-heading\">Why Watch {$title}?</h3>\n";
        $content .= "<!-- /wp:heading -->\n\n";
        
        $content .= "<!-- wp:paragraph -->\n";
        $content .= "<p>With its release in {$year}, {$title} represents the high quality of content available to viewers today. ";
        $content .= "Whether you're looking for entertainment, storytelling, or simply a great viewing experience, this {$content_type} delivers on multiple levels.</p>\n";
        $content .= "<!-- /wp:paragraph -->\n\n";
        
        return $content;
    }

    /**
     * Clean and validate WordPress block content
     */
    private function clean_and_validate_blocks($content) {
        // Remove any stray markdown formatting
        $content = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $content);
        $content = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $content);
        
        // Ensure proper block structure for headings
        $content = preg_replace('/^#{2}\s+(.+)$/m', '<!-- wp:heading -->' . "\n" . '<h2 class="wp-block-heading">$1</h2>' . "\n" . '<!-- /wp:heading -->', $content);
        $content = preg_replace('/^#{3}\s+(.+)$/m', '<!-- wp:heading {"level":3} -->' . "\n" . '<h3 class="wp-block-heading">$1</h3>' . "\n" . '<!-- /wp:heading -->', $content);
        
        // Fix heading blocks without proper attributes
        $content = preg_replace('/<h2([^>]*)>/i', '<h2 class="wp-block-heading"$1>', $content);
        $content = preg_replace('/<h3([^>]*)>/i', '<h3 class="wp-block-heading"$1>', $content);
        
        // Ensure paragraphs have block structure
        $lines = explode("\n", $content);
        $formatted_content = '';
        $in_block = false;
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // Check if this is a block comment
            if (strpos($line, '<!-- wp:') === 0) {
                $in_block = true;
                $formatted_content .= $line . "\n";
            } elseif (strpos($line, '<!-- /wp:') === 0) {
                $formatted_content .= $line . "\n\n";
                $in_block = false;
            } elseif ($in_block) {
                // Inside a block, keep the content
                $formatted_content .= $line . "\n";
            } else {
                // Not in a block, wrap in paragraph if needed
                if (!empty($line) && !strpos($line, '<!--') && !preg_match('/^<h[1-6]/', $line)) {
                    $formatted_content .= "<!-- wp:paragraph -->\n<p>{$line}</p>\n<!-- /wp:paragraph -->\n\n";
                } else {
                    $formatted_content .= $line . "\n";
                }
            }
        }
        
        // Final cleanup
        $content = preg_replace('/\n{3,}/', "\n\n", $formatted_content);
        return trim($content);
    }
}