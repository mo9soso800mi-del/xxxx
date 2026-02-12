<?php
$ViSCO = "8535496684:AAGt9-BayEnfVW1Wwfzvw_0Mi031dQ3TJok";
$apiUrl = "https://api.telegram.org/bot" . $ViSCO;
$DEVDZ = "https://viscodev.x10.mx/apis_gc/api.php";
$activeRequestsFile = 'active_requests.json';
if (!file_exists('sessions')) {
    mkdir('sessions', 0777, true);
}

function loadActiveRequests() {
    global $activeRequestsFile;
    if (file_exists($activeRequestsFile)) {
        $data = file_get_contents($activeRequestsFile);
        return json_decode($data, true) ?: [];
    }
    return [];
}

function saveActiveRequests($requests) {
    global $activeRequestsFile;
    file_put_contents($activeRequestsFile, json_encode($requests));
}

function isActiveRequest($chatId) {
    $requests = loadActiveRequests();
    return isset($requests[$chatId]) && (time() - $requests[$chatId]) < 300;
}

function addActiveRequest($chatId) {
    $requests = loadActiveRequests();
    $requests[$chatId] = time();
    saveActiveRequests($requests);
}

function removeActiveRequest($chatId) {
    $requests = loadActiveRequests();
    if (isset($requests[$chatId])) {
        unset($requests[$chatId]);
        saveActiveRequests($requests);
    }
}

function callImageGenerationAPI($prompt, $numberOfImages = 4, $style = "realistic") {
    global $DEVDZ;
    
    $postData = json_encode([
        'prompt' => $prompt,
        'number' => $numberOfImages,
        'style' => $style
    ]);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $DEVDZ);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'Content-Length: ' . strlen($postData)
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($httpCode != 200) {
        return [
            'success' => false,
            'error' => "Server bilan aloqa muvaffaqiyatsiz: HTTP $httpCode",
            'details' => $error
        ];
    }
    
    $result = json_decode($response, true);
    return $result;
}

function sendMessage($chatId, $text, $replyMarkup = null, $messageId = null, $parseMode = 'HTML') {
    global $apiUrl;
    
    $data = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => $parseMode
    ];
    
    if ($replyMarkup) {
        $data['reply_markup'] = json_encode($replyMarkup);
    }
    
    if ($messageId) {
        $data['message_id'] = $messageId;
        $url = $apiUrl . "/editMessageText";
    } else {
        $url = $apiUrl . "/sendMessage";
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $result = curl_exec($ch);
    curl_close($ch);
    
    return $result;
}

function deleteMessage($chatId, $messageId) {
    global $apiUrl;
    
    $url = $apiUrl . "/deleteMessage?chat_id=" . $chatId . "&message_id=" . $messageId;
    @file_get_contents($url);
}

function answerCallbackQuery($callbackId, $text = "", $showAlert = false) {
    global $apiUrl;
    
    $url = $apiUrl . "/answerCallbackQuery?callback_query_id=" . $callbackId;
    if ($text) {
        $url .= "&text=" . urlencode($text);
    }
    if ($showAlert) {
        $url .= "&show_alert=true";
    }
    
    @file_get_contents($url);
}

function getSessionData($chatId) {
    $sessionFile = "sessions/{$chatId}.json";
    if (file_exists($sessionFile)) {
        $data = file_get_contents($sessionFile);
        return json_decode($data, true);
    }
    return null;
}

function saveSessionData($chatId, $data) {
    $sessionFile = "sessions/{$chatId}.json";
    file_put_contents($sessionFile, json_encode($data));
}

function sendWelcome($chatId, $messageId = null) {
    $welcomeText = "🤖 <b>Sun'iy intellekt tasvir yaratish botiga xush kelibsiz!</b>\n\n";
    $welcomeText .= "✨ <b>Men nima qila olaman?</b>\n";
    $welcomeText .= "• 🎨 Tavsifyingizga asoslangan noyob rasmlar yaratish\n";
    $welcomeText .= "• 🖼️ Tanlash uchun 6 xil uslub\n";
    $welcomeText .= "• 📊 Bir vaqtda 8 tagacha rasm\n\n";
    $welcomeText .= "⚡ <b>Qanday foydalanish?</b>\n";
    $welcomeText .= "1. Sevimli uslubingizni tanlang\n";
    $welcomeText .= "2. Rasm tavsifini yuboring\n";
    $welcomeText .= "3. Natijalarni kuting\n\n";
    $welcomeText .= "💡 <b>Namunalar:</b>\n";
    $welcomeText .= "• <code>Gullar bog'ida yoqimli mushuk</code>\n";
    $welcomeText .= "• <code>Quyosh botishi manzarasi</code>\n\n";
    $welcomeText .= "🎨 <b>Uslubni tanlang:</b>";
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '🏞️ Realistik', 'callback_data' => 'select_style_realistic'],
                ['text' => '🎨 Badiiy', 'callback_data' => 'select_style_artistic']
            ],
            [
                ['text' => '🖼️ Multfilm', 'callback_data' => 'select_style_cartoon'],
                ['text' => '🌀 Abstrakt', 'callback_data' => 'select_style_abstract']
            ],
            [
                ['text' => '🎬 Kinematik', 'callback_data' => 'select_style_cinematic'],
                ['text' => '🖌️ Akvarel', 'callback_data' => 'select_style_watercolor']
            ],
            [
                ['text' => '🎲 Tasodifiy', 'callback_data' => 'select_style_random']
            ]
        ]
    ];
    
    sendMessage($chatId, $welcomeText, $keyboard, $messageId);
}

function showStyleSelected($chatId, $messageId, $style, $styleName) {
    saveSessionData($chatId, [
        'selected_style' => $style,
        'style_name' => $styleName
    ]);
    
    $message = "✅ <b>Uslub tanlandi:</b> " . $styleName . "\n\n";
    $message .= "📝 <b>Endi istagan rasmning tavsifini yuboring</b>\n\n";
    $message .= "💡 <b>Misol:</b> <code>Rangli gullar bog'ida yoqimli mushuk</code>\n\n";
    $message .= "✨ <i>Ushbu uslub keyingi so'rovlaringiz uchun saqlanadi</i>";
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '🔙 Boshqa uslub tanlashga qaytish', 'callback_data' => 'back_to_styles']
            ]
        ]
    ];
    
    sendMessage($chatId, $message, $keyboard, $messageId);
}

function sendWaitingMessage($chatId, $text, $styleName) {
    $message = "☕ <b>Rasmlar yaratilmoqda...</b>\n\n";
    $message .= "🎨 <b>Uslub:</b> " . $styleName . "\n";
    $message .= "📝 <b>Tavsif:</b> " . htmlspecialchars($text) . "\n\n";
    $message .= "⏳ <i>Iltimos, biroz kuting</i>";
    
    $result = sendMessage($chatId, $message);
    $resultData = json_decode($result, true);
    
    if (isset($resultData['result']['message_id'])) {
        return $resultData['result']['message_id'];
    }
    
    return null;
}

function sendImageAlbum($chatId, $imageUrls, $text, $styleName) {
    global $apiUrl;
    
    if (empty($imageUrls)) {
        return false;
    }
    
    $media = [];
    $captionAdded = false;
    
    foreach ($imageUrls as $index => $imageUrl) {
        $media[] = [
            'type' => 'photo',
            'media' => $imageUrl
        ];
        
        if (!$captionAdded && $index === 0) {
            $media[0]['caption'] = "✅ <b>Rasmlar muvaffaqiyatli yaratildi!</b>\n\n";
            $media[0]['caption'] .= "🎨 <b>Uslub:</b> " . $styleName . "\n";
            $media[0]['caption'] .= "📝 <b>Tavsif:</b> <code>" . htmlspecialchars($text) . "</code>\n";
            $media[0]['caption'] .= "📊 <b>Rasmlar soni:</b> " . count($imageUrls) . "\n\n";
            $media[0]['caption'] .= "✨ Yangi uslub tanlang yoki boshqa tavsif yuboring";
            $media[0]['parse_mode'] = 'HTML';
            $captionAdded = true;
        }
    }
    
    $postFields = [
        'chat_id' => $chatId,
        'media' => json_encode($media)
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl . "/sendMediaGroup");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    
    $result = curl_exec($ch);
    curl_close($ch);
    
    return $result;
}

function generateImages($chatId, $text, $style, $styleName) {
    $waitingMessageId = sendWaitingMessage($chatId, $text, $styleName);
    $apiResult = callImageGenerationAPI($text, 4, $style);
    if ($waitingMessageId) {
        deleteMessage($chatId, $waitingMessageId);
    }
    
    if ($apiResult['success']) {
        if (!empty($apiResult['images'])) {
            sendImageAlbum($chatId, $apiResult['images'], $text, $styleName);
        } else {
            $errorMessage = "❌ <b>Kechirasiz, hech qanday rasm yaratilmadi</b>\n\n";
            $errorMessage .= "🎨 <b>Uslub:</b> " . $styleName . "\n";
            $errorMessage .= "📝 <b>Tavsif:</b> " . htmlspecialchars($text) . "\n\n";
            $errorMessage .= "🔄 <b>Boshqa tavsif sinab ko'ring</b>";
            sendMessage($chatId, $errorMessage);
        }
    } else {
        $errorMessage = "❌ <b>Kechirasiz, rasmlarni yaratishda xatolik yuz berdi</b>\n\n";
        $errorMessage .= "🎨 <b>Uslub:</b> " . $styleName . "\n";
        $errorMessage .= "📝 <b>Tavsif:</b> " . htmlspecialchars($text) . "\n\n";
        
        if (isset($apiResult['error'])) {
            $errorMessage .= "🔧 <b>Sabab:</b> " . $apiResult['error'] . "\n\n";
        }
        
        $errorMessage .= "🔄 <b>Biroz vaqt o'tgach yana urinib ko'ring...</b>";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🔙 Boshiga qaytish', 'callback_data' => 'back_to_styles']
                ]
            ]
        ];
        
        sendMessage($chatId, $errorMessage, $keyboard);
    }
    
    removeActiveRequest($chatId);
    return isset($apiResult['success']) ? $apiResult['success'] : false;
}

$update = json_decode(file_get_contents("php://input"), true);

if (isset($update['message'])) {
    $message = $update['message'];
    $chatId = $message['chat']['id'];
    $text = isset($message['text']) ? trim($message['text']) : '';
    
    if ($text == '/start' || $text == '/start@your_bot_username') {
        sendWelcome($chatId);
    } elseif (!empty($text) && $text != '/start') {
        if (isActiveRequest($chatId)) {
            $waitMessage = "⏳ <b>Allaqachon amalga oshirilayotgan so'rov mavjud</b>\n\n";
            $waitMessage .= "Joriy so'rov tugaguniga kuting...";
            sendMessage($chatId, $waitMessage);
            echo "OK";
            exit;
        }
        $sessionData = getSessionData($chatId);
        
        if ($sessionData && isset($sessionData['selected_style'])) {
            $selectedStyle = $sessionData['selected_style'];
            $styleName = $sessionData['style_name'] ?? '🏞️ Realistik';
            
            addActiveRequest($chatId);
            generateImages($chatId, $text, $selectedStyle, $styleName);
        } else {
            $errorMessage = "⚠️ <b>Siz hali uslub tanlamadingiz!</b>\n\n";
            $errorMessage .= "Iltimos, avval /start tugmasini bosing va uslubni tanlang";
            sendMessage($chatId, $errorMessage);
        }
    }
    
} elseif (isset($update['callback_query'])) {
    $callback = $update['callback_query'];
    $chatId = $callback['message']['chat']['id'];
    $messageId = $callback['message']['message_id'];
    $data = $callback['data'];
    
    if ($data == 'back_to_styles') {
        sendWelcome($chatId, $messageId);
        
    } elseif (strpos($data, 'select_style_') === 0) {
        $styleCode = str_replace('select_style_', '', $data);
        
        $styleMap = [
            'realistic' => ['code' => 'realistic', 'name' => '🏞️ Realistik'],
            'artistic' => ['code' => 'artistic', 'name' => '🎨 Badiiy'],
            'cartoon' => ['code' => 'cartoon', 'name' => '🖼️ Multfilm'],
            'abstract' => ['code' => 'abstract', 'name' => '🌀 Abstrakt'],
            'cinematic' => ['code' => 'cinematic', 'name' => '🎬 Kinematik'],
            'watercolor' => ['code' => 'watercolor', 'name' => '🖌️ Akvarel'],
            'random' => [
                'code' => ['realistic', 'artistic', 'cartoon', 'abstract', 'cinematic', 'watercolor'][array_rand(['realistic', 'artistic', 'cartoon', 'abstract', 'cinematic', 'watercolor'])],
                'name' => '🎲 Tasodifiy'
            ]
        ];
        
        if (isset($styleMap[$styleCode])) {
            $styleInfo = $styleMap[$styleCode];
            $selectedStyle = $styleInfo['code'];
            $styleName = $styleInfo['name'];
            
            if ($styleCode == 'random') {
                $randomStyles = [
                    'realistic' => '🏞️ Realistik',
                    'artistic' => '🎨 Badiiy',
                    'cartoon' => '🖼️ Multfilm',
                    'abstract' => '🌀 Abstrakt',
                    'cinematic' => '🎬 Kinematik',
                    'watercolor' => '🖌️ Akvarel'
                ];
                $styleName = $randomStyles[$selectedStyle] ?? '🎲 Tasodifiy';
            }
            
            showStyleSelected($chatId, $messageId, $selectedStyle, $styleName);
        }
    }
    
    answerCallbackQuery($callback['id']);
}

echo "OK";
?>