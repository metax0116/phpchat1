<?php
// .env ファイルをライブラリなしで読み込む簡易ロジック
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

$response_text = "";

// フォームが送信された場合の処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['user_input'])) {
    $api_key = $_ENV['OPENAI_API_KEY'] ?? '';
    $user_input = $_POST['user_input'];

    if (empty($api_key)) {
        $response_text = "エラー: .envファイルにAPIキーが設定されていません。";
    } else {
        // cURLを使ってOpenAI APIを叩く
        $ch = curl_init();
        
        $data = [
            'model' => 'gpt-5-mini', // 指定されたモデル
            'messages' => [
                [
                    'role' => 'system', 
                    'content' => 'あなたは言語解析アシスタントです。ユーザーの入力から「最も重要な単語を1つだけ」選び出し、その「原語」と「英訳」を以下のプレーンテキスト形式のみで返してください。余計な挨拶や説明は一切不要です。形式: 原語 - 英訳'
                ],
                ['role' => 'user', 'content' => $user_input]
            ],
            'temperature' => 0.3
        ];

        curl_setopt($ch, CURLOPT_URL, "https://api.openai.com/v1/chat/completions");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: Bearer " . $api_key
        ]);

        $result = curl_exec($ch);
        
        if (curl_errno($ch)) {
            $response_text = 'Curl エラー: ' . curl_error($ch);
        } else {
            $decoded = json_decode($result, true);
            if (isset($decoded['error'])) {
                $response_text = "API エラー: " . $decoded['error']['message'];
            } else {
                $response_text = $decoded['choices'][0]['message']['content'] ?? '応答がありませんでした。';
            }
        }
        curl_close($ch);
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>重要語抽出ボット</title>
    <style>
        body { font-family: sans-serif; max-width: 600px; margin: 2rem auto; padding: 0 1rem; }
        textarea { width: 100%; height: 100px; margin-bottom: 10px; }
        button { padding: 10px 20px; cursor: pointer; }
        .result { background: #f4f4f4; padding: 15px; margin-top: 20px; border-radius: 5px; border: 1px solid #ddd; }
        pre { margin: 0; white-space: pre-wrap; font-size: 1.2em; font-weight: bold; }
    </style>
</head>
<body>
    <h2>重要語・英訳抽出チャットボット</h2>
    
    <form method="post">
        <textarea name="user_input" placeholder="ここに文章を入力してください（例：今日は美味しい寿司を食べに行きました。）" required></textarea><br>
        <button type="submit">送信</button>
    </form>

    <?php if ($response_text): ?>
        <div class="result">
            <p>抽出結果:</p>
            <pre><?php echo htmlspecialchars($response_text, ENT_QUOTES, 'UTF-8'); ?></pre>
        </div>
    <?php endif; ?>
</body>
</html>