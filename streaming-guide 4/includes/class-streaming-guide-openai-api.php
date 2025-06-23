<?php
if (!defined('ABSPATH')) {
    exit;
}

class Streaming_Guide_OpenAI_API {
    private $api_key;
    private $api_url = 'https://api.openai.com/v1/chat/completions';
    private static $last_request_time = 0;
    private static $request_count = 0;
    
    public function __construct() {
        $this->api_key = get_option('streaming_guide_openai_api_key');
    }
    
    /**
     * Make API request to OpenAI with rate limiting
     */
    public function make_request($messages, $temperature = 0.7, $max_tokens = 4000) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', 'OpenAI API key not configured');
        }
        
        // Rate limiting - max 3 requests per minute for free tier, 60 for paid
        $current_time = time();
        if (self::$last_request_time > 0) {
            $time_since_last = $current_time - self::$last_request_time;
            
            // If less than 2 seconds since last request, wait
            if ($time_since_last < 2) {
                sleep(2 - $time_since_last);
            }
        }
        
        self::$last_request_time = time();
        self::$request_count++;
        
        $args = array(
            'method' => 'POST',
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'model' => 'gpt-4o-mini', // Use faster, cheaper model to avoid rate limits
                'messages' => $messages,
                'temperature' => $temperature,
                'max_tokens' => $max_tokens
            )),
            'timeout' => 120, // 2 minute timeout
            'sslverify' => true,
        );
        
        $response = wp_remote_post($this->api_url, $args);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($status_code !== 200) {
            $error_data = json_decode($body, true);
            $error_message = isset($error_data['error']['message']) ? $error_data['error']['message'] : 'Unknown API error';
            
            // Handle rate limiting specifically
            if ($status_code === 429) {
                // Wait 60 seconds before next request
                self::$last_request_time = time() + 60;
                return new WP_Error('rate_limit', 'OpenAI API rate limit exceeded. Waiting 60 seconds.');
            }
            
            return new WP_Error('api_error', $error_message, ['status_code' => $status_code]);
        }
        
        $data = json_decode($body, true);
        
        if (!isset($data['choices'][0]['message']['content'])) {
            return new WP_Error('invalid_response', 'Invalid OpenAI response format');
        }
        
        return $data['choices'][0]['message']['content'];
    }
    /**
 * Generic content generation method
 */
public function generate_content($prompt, $temperature = 0.7, $max_tokens = 4000) {
    $messages = array(
        array(
            'role' => 'system',
            'content' => 'You are a professional entertainment journalist writing for a streaming guide website. Write engaging, informative articles about movies and TV shows.'
        ),
        array(
            'role' => 'user',
            'content' => $prompt
        )
    );
    
    return $this->make_request($messages, $temperature, $max_tokens);
    }
    
    /**
     * Generate article title
     */
    public function generate_title($platform, $content_type, $movies_data, $date_context) {
        $messages = array(
            array(
                'role' => 'system',
                'content' => 'You are a professional entertainment writer. Create ONE engaging, SEO-friendly article title about streaming platforms.'
            ),
            array(
                'role' => 'user',
                'content' => "Create ONE compelling article title for new content on {$platform} for {$date_context}. " .
                           "Content includes: " . json_encode($movies_data) . ". " .
                           "Make it clickable and informative. Include the platform name and timeframe. " .
                           "Return only ONE title, no numbering, no alternatives."
            )
        );
        
        return $this->make_request($messages, 0.5); // Lower temperature for more consistent results
    }
    
    /**
     * Generate article content
     */
    public function generate_article($platform, $content_type, $title, $content_data) {
        $system_prompt = "You are a professional entertainment journalist writing for a streaming guide website. " .
                        "Write engaging, informative articles about movies and TV shows on streaming platforms. " .
                        "Use a conversational but professional tone. Include release dates, cast information, " .
                        "and why the content is worth watching. Always include a clear conclusion.";
        
        $content_prompt = "Write a comprehensive article with the title: \"{$title}\"\n\n" .
                         "Content Information:\n" . json_encode($content_data, JSON_PRETTY_PRINT) . "\n\n" .
                         "Article Structure:\n" .
                         "1. Brief introduction about what's new this week\n" .
                         "2. Highlight 3-5 standout movies/shows with details\n" .
                         "3. Quick mentions of other notable additions\n" .
                         "4. Conclusion with viewing recommendations\n\n" .
                         "Format in HTML with proper headings (h2, h3) and paragraphs.";
        
        $messages = array(
            array(
                'role' => 'system',
                'content' => $system_prompt
            ),
            array(
                'role' => 'user',
                'content' => $content_prompt
            )
        );
        
        return $this->make_request($messages);
    }
    
    /**
     * Generate article summary/excerpt
     */
    public function generate_summary($article_content) {
        $messages = array(
            array(
                'role' => 'system',
                'content' => 'You create concise summaries for articles about streaming content.'
            ),
            array(
                'role' => 'user',
                'content' => "Create a brief summary (50-80 words) of this article:\n\n{$article_content}"
            )
        );
        
        return $this->make_request($messages, 0.5);
    }
    
    /**
     * Generate SEO meta description
     */
    public function generate_meta_description($title, $article_content) {
        $messages = array(
            array(
                'role' => 'system',
                'content' => 'You create SEO-optimized meta descriptions for streaming entertainment articles.'
            ),
            array(
                'role' => 'user',
                'content' => "Create an SEO meta description (max 155 characters) for this article:\n" .
                           "Title: {$title}\n\n" .
                           "Content: {$article_content}\n\n" .
                           "Include the platform name and key content highlights."
            )
        );
        
        return $this->make_request($messages, 0.3);
    }
    
    /**
     * Generate tags for the article
     */
    public function generate_tags($platform, $content_data) {
        $messages = array(
            array(
                'role' => 'system',
                'content' => 'You generate relevant tags for streaming entertainment articles.'
            ),
            array(
                'role' => 'user',
                'content' => "Generate 5-10 relevant tags for an article about {$platform} content:\n" .
                           json_encode($content_data) . "\n\n" .
                           "Return as comma-separated list. Include platform name, content types, and relevant keywords."
            )
        );
        
        return $this->make_request($messages, 0.3);
    }
    
    /**
     * Generate article content for weekly/monthly articles
     */
    public function generate_weekly_monthly_article($platform, $content_type, $title, $content_data) {
        $system_prompt = "You are a professional entertainment journalist writing for a streaming guide website. " .
                        "Write engaging, informative articles about movies and TV shows on streaming platforms. " .
                        "Use a conversational but professional tone. Keep movies and TV shows in separate sections. " .
                        "Always include release/air dates when provided.";
        
        $content_prompt = "Write a comprehensive article with the title: \"{$title}\"\n\n" .
                         "Content Information:\n" . json_encode($content_data, JSON_PRETTY_PRINT) . "\n\n" .
                         "Article Structure:\n" .
                         "1. Brief introduction\n" .
                         "2. Separate sections for movies and TV shows\n" .
                         "3. For each item, include:\n" .
                         "   - Title\n" .
                         "   - Release/air date (if available)\n" .
                         "   - Detailed description\n" .
                         "   - Rating (if available)\n" .
                         "4. Conclude with viewing recommendations\n\n" .
                         "Format in plain HTML with proper headings (h2, h3) and paragraphs.";
        
        $messages = array(
            array(
                'role' => 'system',
                'content' => $system_prompt
            ),
            array(
                'role' => 'user',
                'content' => $content_prompt
            )
        );
        
        return $this->make_request($messages, 0.7, 4000);
    }
    
    /**
     * Generate article content for spotlights
     */
    public function generate_spotlight_article($platform, $title, $content_data) {
        $system_prompt = "You are a sharp, seasoned entertainment journalist and film critic.".
                        "You write rich, smart, well-crafted reviews for a digital magazine covering streaming films and series." .
                        "Your tone is polished but accessible—sometimes witty, sometimes analytical—and you aren't afraid to share strong opinions. " .
                        "You blend critical insight with entertainment value, and your work reads like something you'd find in Variety, The Guardian, or IndieWire.";
        
        $content_prompt = "Write a professional, engaging movie review for the following title: \"{$title}\".\n\n" .
                    "Here is the metadata and context about the film:\n\n" . json_encode($content_data, JSON_PRETTY_PRINT) . "\n\n" .
                    "Review Guidelines:\n" .
                    "- Write naturally and organically, not by rigid outline. Do not follow a strict template. Each review should feel like a fresh piece of film criticism.\n" .
                    "- Use rich, thoughtful language and form your own critical perspective.\n" .
                    "- It's okay to describe the premise and plot setup, especially if it helps set the stage—but avoid full spoilers.\n" .
                    "- Mention key performances, direction, cinematography, script quality, and tone as appropriate.\n" .
                    "- Incorporate details about the filmmaker or lead actors if relevant to the analysis.\n" .
                    "- Sometimes compare the film to others in the genre or a director's previous work, but only if it feels relevant.\n" .
                    "- The review should occasionally be more story-led, and other times more analysis-heavy, depending on what makes sense for the movie.\n" .
                    "- A conclusion is optional, but end with a memorable final thought, insight, or take.\n" .
                    "- Final length should feel like a well-written, typical movie review—anywhere from 800 to 1,200 words is ideal.\n\n" .
                    "IMPORTANT: Write flowing paragraphs with NO subheadings, section headers, or HTML heading tags (h1, h2, h3, etc.). Create one continuous review.\n" .
                    "Avoid sounding formulaic—no two reviews should follow the same structure. Prioritize style, voice, and clear opinion.";
        
        $messages = array(
            array(
                'role' => 'system',
                'content' => $system_prompt
            ),
            array(
                'role' => 'user',
                'content' => $content_prompt
            )
        );
        
        return $this->make_request($messages, 0.7, 6000);
    }
}