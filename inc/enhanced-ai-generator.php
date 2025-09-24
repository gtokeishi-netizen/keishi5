<?php
/**
 * Enhanced AI Content Generator
 * Advanced AI generation with context awareness and SEO optimization
 * 
 * @package Grant_Insight_Perfect
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class GI_Enhanced_AI_Generator {
    
    private $api_key;
    private $model = 'gpt-3.5-turbo';
    
    public function __construct() {
        // Get API key from options or constants
        $this->api_key = defined('OPENAI_API_KEY') ? OPENAI_API_KEY : get_option('gi_openai_api_key', '');
        
        add_action('wp_ajax_gi_smart_generate', array($this, 'handle_smart_generation'));
        add_action('wp_ajax_gi_regenerate_content', array($this, 'handle_regeneration'));
        add_action('wp_ajax_gi_contextual_fill', array($this, 'handle_contextual_fill'));
    }
    
    /**
     * Smart content generation based on existing fields
     */
    public function handle_smart_generation() {
        check_ajax_referer('gi_ai_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die('Permission denied');
        }
        
        $existing_data = $this->sanitize_input($_POST['existing_data'] ?? []);
        $target_field = sanitize_text_field($_POST['target_field'] ?? '');
        $generation_mode = sanitize_text_field($_POST['mode'] ?? 'smart_fill');
        
        try {
            $generated_content = $this->generate_contextual_content($existing_data, $target_field, $generation_mode);
            
            wp_send_json_success([
                'content' => $generated_content,
                'field' => $target_field,
                'mode' => $generation_mode,
                'context_used' => !empty($existing_data)
            ]);
            
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'fallback' => $this->get_fallback_content($target_field, $existing_data)
            ]);
        }
    }
    
    /**
     * Handle content regeneration
     */
    public function handle_regeneration() {
        check_ajax_referer('gi_ai_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die('Permission denied');
        }
        
        $existing_data = $this->sanitize_input($_POST['existing_data'] ?? []);
        $target_field = sanitize_text_field($_POST['target_field'] ?? '');
        $current_content = sanitize_textarea_field($_POST['current_content'] ?? '');
        $regeneration_type = sanitize_text_field($_POST['type'] ?? 'improve');
        
        try {
            $regenerated_content = $this->regenerate_content($existing_data, $target_field, $current_content, $regeneration_type);
            
            wp_send_json_success([
                'content' => $regenerated_content,
                'original' => $current_content,
                'type' => $regeneration_type
            ]);
            
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'fallback' => $this->improve_content_simple($current_content, $target_field)
            ]);
        }
    }
    
    /**
     * Handle contextual filling of multiple fields
     */
    public function handle_contextual_fill() {
        check_ajax_referer('gi_ai_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die('Permission denied');
        }
        
        $existing_data = $this->sanitize_input($_POST['existing_data'] ?? []);
        $empty_fields = $_POST['empty_fields'] ?? [];
        
        try {
            $filled_content = $this->fill_empty_fields($existing_data, $empty_fields);
            
            wp_send_json_success([
                'filled_fields' => $filled_content,
                'context_data' => $existing_data
            ]);
            
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'partial_fill' => $this->get_fallback_fills($empty_fields)
            ]);
        }
    }
    
    /**
     * Generate contextual content based on existing data
     */
    private function generate_contextual_content($existing_data, $target_field, $mode) {
        // Build context from existing data
        $context = $this->build_context_prompt($existing_data);
        
        // Field-specific generation prompts
        $field_prompts = $this->get_field_specific_prompts();
        $field_prompt = $field_prompts[$target_field] ?? $field_prompts['default'];
        
        // SEO optimization instructions
        $seo_instructions = $this->get_seo_instructions($target_field);
        
        // Build the complete prompt
        $prompt = $this->build_generation_prompt($context, $field_prompt, $seo_instructions, $mode);
        
        // Call AI API
        return $this->call_openai_api($prompt);
    }
    
    /**
     * Build context prompt from existing data
     */
    private function build_context_prompt($data) {
        $context_parts = [];
        
        if (!empty($data['title'])) {
            $context_parts[] = "助成金名: {$data['title']}";
        }
        
        if (!empty($data['organization'])) {
            $context_parts[] = "実施機関: {$data['organization']}";
        }
        
        if (!empty($data['max_amount'])) {
            $context_parts[] = "最大金額: {$data['max_amount']}";
        }
        
        if (!empty($data['deadline'])) {
            $context_parts[] = "締切: {$data['deadline']}";
        }
        
        if (!empty($data['categories'])) {
            $categories = is_array($data['categories']) ? implode('、', $data['categories']) : $data['categories'];
            $context_parts[] = "カテゴリー: {$categories}";
        }
        
        if (!empty($data['prefectures'])) {
            $prefectures = is_array($data['prefectures']) ? implode('、', $data['prefectures']) : $data['prefectures'];
            $context_parts[] = "対象地域: {$prefectures}";
        }
        
        if (!empty($data['content'])) {
            $content_excerpt = mb_substr(strip_tags($data['content']), 0, 200);
            $context_parts[] = "既存内容: {$content_excerpt}...";
        }
        
        return implode("\n", $context_parts);
    }
    
    /**
     * Get field-specific generation prompts
     */
    private function get_field_specific_prompts() {
        return [
            'post_title' => [
                'instruction' => '魅力的で検索されやすい助成金タイトルを生成してください',
                'requirements' => '30-60文字、キーワードを含む、具体的で分かりやすい',
                'examples' => '「令和6年度IT導入支援事業補助金」「中小企業デジタル化促進助成金」'
            ],
            'post_content' => [
                'instruction' => '詳細で有用な助成金説明文を生成してください',
                'requirements' => '500-1500文字、SEO対策済み、見出し構造を含む、申請方法まで網羅',
                'structure' => '概要→対象者→支給内容→申請方法→注意事項'
            ],
            'post_excerpt' => [
                'instruction' => '簡潔で魅力的な助成金概要を生成してください',
                'requirements' => '100-200文字、要点を簡潔に、検索結果で目立つ内容',
                'focus' => '対象者、金額、メリットを明確に'
            ],
            'eligibility_criteria' => [
                'instruction' => '具体的で分かりやすい対象者・応募要件を生成してください',
                'requirements' => '箇条書き形式、具体的な条件、除外条件も含む',
                'style' => '「・」で始まる箇条書き、分かりやすい日本語'
            ],
            'application_process' => [
                'instruction' => 'ステップバイステップの申請手順を生成してください',
                'requirements' => '番号付きリスト、必要書類、期間、注意点を含む',
                'format' => '1. 〜、2. 〜の形式'
            ],
            'required_documents' => [
                'instruction' => '必要書類一覧を生成してください',
                'requirements' => '具体的な書類名、取得方法、注意点',
                'format' => '箇条書き、カテゴリー別に整理'
            ],
            'default' => [
                'instruction' => 'この助成金に関する有用な情報を生成してください',
                'requirements' => '正確で実用的、SEO対策済み',
                'tone' => '専門的だが分かりやすい'
            ]
        ];
    }
    
    /**
     * Get SEO instructions for specific fields
     */
    private function get_seo_instructions($field) {
        $seo_keywords = ['助成金', '補助金', '支援', '申請', '中小企業', 'スタートアップ'];
        
        switch ($field) {
            case 'post_title':
                return "SEO要件: 主要キーワードを自然に含める。検索意図に合致。32文字以内推奨。";
            case 'post_content':
                return "SEO要件: 関連キーワードを適度に配置。見出し(H2,H3)を使用。内部リンク機会を作る。ユーザーの検索意図に応える。";
            case 'post_excerpt':
                return "SEO要件: メタディスクリプションとしても機能。クリック誘導する内容。主要キーワード含む。";
            default:
                return "SEO要件: 関連キーワードを自然に含める。ユーザーに価値ある情報を提供。";
        }
    }
    
    /**
     * Build complete generation prompt
     */
    private function build_generation_prompt($context, $field_config, $seo_instructions, $mode) {
        $prompt = "あなたは助成金・補助金の専門家です。以下の情報を参考に、高品質な内容を生成してください。\n\n";
        
        if (!empty($context)) {
            $prompt .= "【既存情報】\n{$context}\n\n";
        }
        
        $prompt .= "【生成要件】\n";
        $prompt .= "目的: {$field_config['instruction']}\n";
        $prompt .= "要件: {$field_config['requirements']}\n";
        $prompt .= "{$seo_instructions}\n\n";
        
        if (isset($field_config['structure'])) {
            $prompt .= "構成: {$field_config['structure']}\n";
        }
        
        if (isset($field_config['format'])) {
            $prompt .= "形式: {$field_config['format']}\n";
        }
        
        $prompt .= "\n【生成モード】\n";
        switch ($mode) {
            case 'creative':
                $prompt .= "クリエイティブで魅力的な表現を重視してください。";
                break;
            case 'professional':
                $prompt .= "専門的で正確な表現を重視してください。";
                break;
            case 'seo_focused':
                $prompt .= "SEO効果を最大化する内容を重視してください。";
                break;
            default:
                $prompt .= "バランス良く実用的な内容を生成してください。";
        }
        
        $prompt .= "\n\n生成内容のみを出力してください（説明文は不要）:";
        
        return $prompt;
    }
    
    /**
     * Regenerate existing content with improvements
     */
    private function regenerate_content($existing_data, $field, $current_content, $type) {
        $context = $this->build_context_prompt($existing_data);
        
        $prompt = "以下の内容を{$type}してください。\n\n";
        $prompt .= "【現在の内容】\n{$current_content}\n\n";
        
        if (!empty($context)) {
            $prompt .= "【参考情報】\n{$context}\n\n";
        }
        
        switch ($type) {
            case 'improve':
                $prompt .= "【改善要件】\n- より分かりやすく\n- SEO効果を向上\n- 専門性を高める\n- 文章の流れを改善";
                break;
            case 'shorten':
                $prompt .= "【短縮要件】\n- 要点を保持\n- 50%程度に短縮\n- 重要情報は残す";
                break;
            case 'expand':
                $prompt .= "【拡張要件】\n- より詳細に\n- 具体例を追加\n- 関連情報を補完";
                break;
            case 'seo_optimize':
                $prompt .= "【SEO最適化要件】\n- キーワード密度を適正化\n- 見出し構造を改善\n- 検索意図に最適化";
                break;
        }
        
        $prompt .= "\n\n改善された内容のみを出力してください:";
        
        return $this->call_openai_api($prompt);
    }
    
    /**
     * Fill multiple empty fields based on context
     */
    private function fill_empty_fields($existing_data, $empty_fields) {
        $context = $this->build_context_prompt($existing_data);
        $filled_content = [];
        
        foreach ($empty_fields as $field) {
            try {
                $field_prompts = $this->get_field_specific_prompts();
                $field_config = $field_prompts[$field] ?? $field_prompts['default'];
                
                $prompt = "以下の情報を参考に、{$field}の内容を生成してください。\n\n";
                $prompt .= "【参考情報】\n{$context}\n\n";
                $prompt .= "【要件】\n{$field_config['instruction']}\n{$field_config['requirements']}\n\n";
                $prompt .= "生成内容のみを出力してください:";
                
                $filled_content[$field] = $this->call_openai_api($prompt);
                
                // Rate limiting
                sleep(1);
                
            } catch (Exception $e) {
                $filled_content[$field] = $this->get_fallback_content($field, $existing_data);
            }
        }
        
        return $filled_content;
    }
    
    /**
     * Call OpenAI API
     */
    private function call_openai_api($prompt) {
        if (empty($this->api_key)) {
            throw new Exception('OpenAI API key not configured');
        }
        
        $url = 'https://api.openai.com/v1/chat/completions';
        
        $data = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'あなたは助成金・補助金の専門家です。正確で実用的な情報を提供し、SEOも考慮した高品質な日本語コンテンツを生成してください。'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'max_tokens' => 2000,
            'temperature' => 0.7
        ];
        
        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($data),
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            throw new Exception('API request failed: ' . $response->get_error_message());
        }
        
        $response_body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($response_body['error'])) {
            throw new Exception('OpenAI API error: ' . $response_body['error']['message']);
        }
        
        if (!isset($response_body['choices'][0]['message']['content'])) {
            throw new Exception('Invalid API response format');
        }
        
        return trim($response_body['choices'][0]['message']['content']);
    }
    
    /**
     * Get fallback content when AI fails
     */
    private function get_fallback_content($field, $existing_data = []) {
        $fallbacks = [
            'post_title' => $this->generate_title_fallback($existing_data),
            'post_content' => $this->generate_content_fallback($existing_data),
            'post_excerpt' => $this->generate_excerpt_fallback($existing_data),
            'eligibility_criteria' => "・中小企業、個人事業主が対象\n・法人設立から3年以内\n・従業員数50名以下\n・過去に同様の助成金を受給していないこと",
            'application_process' => "1. 申請書類の準備\n2. オンライン申請システムでの登録\n3. 必要書類のアップロード\n4. 審査結果の通知待ち\n5. 採択後の手続き",
            'required_documents' => "・申請書（指定様式）\n・会社概要書\n・事業計画書\n・見積書\n・直近の決算書\n・履歴事項全部証明書"
        ];
        
        return $fallbacks[$field] ?? "こちらの項目について詳細な情報をご確認ください。";
    }
    
    /**
     * Generate fallback fills for multiple fields
     */
    private function get_fallback_fills($fields) {
        $fills = [];
        foreach ($fields as $field) {
            $fills[$field] = $this->get_fallback_content($field);
        }
        return $fills;
    }
    
    /**
     * Generate title fallback
     */
    private function generate_title_fallback($data) {
        $org = !empty($data['organization']) ? $data['organization'] : '各自治体';
        $category = !empty($data['categories'][0]) ? $data['categories'][0] : 'ビジネス支援';
        return "{$org} {$category}助成金・補助金制度";
    }
    
    /**
     * Generate content fallback  
     */
    private function generate_content_fallback($data) {
        $title = !empty($data['title']) ? $data['title'] : '助成金制度';
        $org = !empty($data['organization']) ? $data['organization'] : '実施機関';
        
        return "{$title}は、{$org}が実施する事業者支援制度です。\n\n" .
               "## 制度概要\n" .
               "事業の発展と成長を支援するための助成金制度です。\n\n" .
               "## 対象者\n" .
               "中小企業、個人事業主等が対象となります。\n\n" .
               "## 申請方法\n" .
               "詳細は実施機関の公式サイトをご確認ください。";
    }
    
    /**
     * Generate excerpt fallback
     */
    private function generate_excerpt_fallback($data) {
        $org = !empty($data['organization']) ? $data['organization'] : '実施機関';
        $amount = !empty($data['max_amount']) ? $data['max_amount'] : '規定の金額';
        
        return "{$org}による事業者向け助成金制度。最大{$amount}の支援を受けることができます。申請条件や手続き方法について詳しくご紹介します。";
    }
    
    /**
     * Simple content improvement (non-AI)
     */
    private function improve_content_simple($content, $field) {
        // Simple text improvements without AI
        $content = trim($content);
        
        // Add structure if missing
        if ($field === 'post_content' && strpos($content, '##') === false) {
            return "## 概要\n{$content}\n\n## 詳細情報\n申請や条件について、詳細は実施機関にお問い合わせください。";
        }
        
        return $content;
    }
    
    /**
     * Sanitize input data
     */
    private function sanitize_input($data) {
        if (!is_array($data)) {
            return [];
        }
        
        $sanitized = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = array_map('sanitize_text_field', $value);
            } else {
                $sanitized[$key] = sanitize_textarea_field($value);
            }
        }
        
        return $sanitized;
    }
}

// Initialize the enhanced AI generator
new GI_Enhanced_AI_Generator();