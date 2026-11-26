<?php
declare(strict_types=1);

// ============================================================
// ربات تلگرام + Pollinations - نسخه‌ی PHP (حالت Long Polling)
// ============================================================

function env(string $name, string $default = ''): string
{
    $value = getenv($name);
    return ($value === false || $value === '') ? $default : $value;
}

date_default_timezone_set(env('TIMEZONE', 'UTC'));
set_time_limit(0);
ignore_user_abort(true);

// ----- تنظیمات (مقادیر مستقیماً وارد شده‌اند) -----
define('BOT_TOKEN', '204950570:AAFzycx2TKuxm3kLWO8NCN7efK4xDEbEzRE');
define('ADMIN_IDS_RAW', '8866404328');
define('DEFAULT_DAILY_LIMIT', 50);
define('POLLINATIONS_API_KEY', 'sk_bJg5A28imMHYUBIGJU23NYuqaRUQsyku');
define('WEBHOOK_SECRET', '');
define('STORAGE_DIR', rtrim(env('STORAGE_DIR', __DIR__ . '/data'), '/'));

define('API', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');
define('FILE_API', 'https://api.telegram.org/file/bot' . BOT_TOKEN . '/');

// ============================================================
// ثابت‌ها
// ============================================================
const CHAT_URL = 'https://gen.pollinations.ai/v1/chat/completions';
const CHAT_MODEL = 'openai/gpt-5.4-nano';
const SYSTEM_PROMPT = "You are a friendly, helpful assistant. Your users are mostly Persian (Farsi) speakers. By default, reply in Persian. If the user writes in English, reply in English. Keep the same language as the user's latest message.";
const IMAGE_PROMPT_INSTRUCTION = "Translate the user's image request into one detailed English prompt for a text-to-image model. Describe subject, style, lighting and composition. Output only the prompt, no quotes, no explanation.";
const MAX_HISTORY = 20;
const MAX_ANSWER_CHARS = 4000;
const MAX_TRACKED_MESSAGES = 1000;
const MAX_SPEECH_CHARS = 800;

const IMAGE_URL = 'https://gen.pollinations.ai/image/';
const IMAGE_MODEL = 'flux';
const EDIT_MODEL = 'kontext';
const IMAGE_SIZE = 1024;
const AUDIO_URL = 'https://gen.pollinations.ai/audio/';
const DEFAULT_VOICE = 'alloy';
const VOICE_OPTIONS = ['alloy', 'nova', 'onyx', 'shimmer', 'echo', 'fable'];
const EDIT_ENABLED = true;
const USERS_PER_PAGE = 8;

// ----- نوار پیشرفت -----
const PROGRESS_INTERVAL = 1.4;
const PROGRESS_MAX_BEFORE_DONE = 92;
const PROGRESS_BAR_WIDTH = 12;
const SPINNER = ['◐', '◓', '◑', '◒'];
const MIN_WAIT_SECONDS = 1.5;

// ----- حالت‌های منتظر ورودی -----
const MODE_IMAGE = 'image';
const MODE_EDIT_PHOTO = 'edit_photo';
const MODE_EDIT_PROMPT = 'edit_prompt';

// ----- برچسب‌ها -----
const LABEL_CHAT = '✧ در حال فکر کردن';
const LABEL_VOICE = '♫ در حال ساخت ویس';
const LABEL_IMAGE = '✦ در حال ساخت عکس';
const LABEL_EDIT = '✎ در حال ویرایش عکس';
const LABEL_CLEAR = '✖ در حال پاک کردن گفتگو';

// ----- دکمه‌های کیبورد اصلی -----
const BTN_IMAGE = 'ساخت عکس';
const BTN_EDIT = 'ویرایش عکس';
const BTN_VOICE = 'پاسخ با ویس';
const BTN_VOICE_SETTINGS = 'تنظیمات صدا';
const BTN_CLEAR = 'حذف تاریخچه';
const BTN_HELP = 'راهنما';
const BTN_CANCEL = 'انصراف';
const BTN_BACK = 'بازگشت';

const BUTTON_SYMBOL = [
    BTN_IMAGE => '✦', BTN_EDIT => '✎', BTN_VOICE => '♫', BTN_VOICE_SETTINGS => '❖',
    BTN_CLEAR => '✖', BTN_HELP => '✿', BTN_CANCEL => '⊘', BTN_BACK => '➜',
];
const VOICE_SYMBOL = '♪';
const BUTTON_STYLES = [
    BTN_IMAGE => 'primary', BTN_EDIT => 'primary', BTN_VOICE => 'success',
    BTN_VOICE_SETTINGS => 'primary', BTN_HELP => 'primary',
    BTN_CLEAR => 'danger', BTN_CANCEL => 'danger',
];
const BUTTON_KEYS = [
    BTN_IMAGE, BTN_EDIT, BTN_VOICE, BTN_VOICE_SETTINGS,
    BTN_CLEAR, BTN_HELP, BTN_CANCEL, BTN_BACK,
];

// ----- متن‌ها -----
const PROMO = '✦ ربات‌ها و کانال ما: @NabAfzar';
const START_TEXT = "✦ سلام! خوش اومدی.\n➜ هر سوالی داری بپرس؛ فارسی یا انگلیسی. گفتگوی قبلی‌ات هم ذخیره می‌شه.\n\n✦ عکس بساز، ✎ عکس بفرست و ویرایشش کن، ♫ حالت ویس را روشن کن یا ❖ صدایش را عوض کن.\n\n" . PROMO;
const HELP_TEXT = "✿ راهنمای ربات\n\n➜ گفتگو: هر متنی بفرستی جواب می‌دهم. به فارسی بنویس یا انگلیسی.\n✦ ساخت عکس: دکمه را بزن و توضیح عکس را بفرست (فارسی یا انگلیسی).\n✎ ویرایش عکس: دکمه را بزن، یک عکس بفرست، بعد بگو چه تغییری بدهم.\n♫ پاسخ با ویس: روشن یا خاموش کردن پاسخ صوتی.\n❖ تنظیمات صدا: انتخاب صدای ویس.\n✖ حذف تاریخچه: پاک کردن گفتگو و همه‌ی پیام‌های این چت.\n⊘ انصراف: لغو کار جاری.\n\n" . PROMO;
const IMAGE_ASK_TEXT = "✦ توضیح عکسی که می‌خواهی را بفرست.\nمثلاً: یک گربه روی ماه با لباس فضانوردی";
const EDIT_ASK_PHOTO = '➜ یک عکس بفرست تا ویرایشش کنم.';
const EDIT_ASK_PROMPT = "✎ بگو چه تغییری روی عکس بدهم.\nمثلاً: پس‌زمینه را به ساحل تغییر بده";
const PHOTO_MISSING_TEXT = '➜ عکس دریافت نشد. لطفاً یک عکس بفرست.';
const CONTINUE_TEXT = '➜ برای ادامه، سوال بپرس یا از دکمه‌ها استفاده کن.';
const VOICE_SETTINGS_TEXT = "❖ صدای فعلی: %s\nیکی از صداهای زیر را انتخاب کن:";
const VOICE_CHANGED_TEXT = '✓ صدای ویس به %s تغییر کرد.';
const IMAGE_DONE_TEXT = '✓ عکس شما آماده است';
const EDIT_DONE_TEXT = '✓ عکس ویرایش شد';
const VOICE_DONE_TEXT = '♫ پاسخ به‌صورت ویس ارسال شد.';
const IMAGE_ERROR = '✖ متأسفم، ساخت عکس انجام نشد. کمی بعد دوباره امتحان کن.';
const EDIT_ERROR = '✖ متأسفم، ویرایش عکس انجام نشد. مطمئن شو عکس را درست فرستادی.';
const VOICE_ON_TEXT = '♫ حالت ویس روشن شد. از این به بعد پاسخ‌ها به‌صورت ویس ارسال می‌شوند.';
const VOICE_OFF_TEXT = '✎ حالت ویس خاموش شد. از این به بعد پاسخ‌ها متنی هستند.';
const HISTORY_CLEARED_TEXT = '✓ گفتگو و پیام‌های این چت پاک شد. از این به بعد از صفر شروع می‌کنیم.';
const CANCEL_TEXT = '⊘ لغو شد.';
const AI_REPLY_ERROR = '✖ متأسفم، الان نتونستم جواب بدم. کمی بعد دوباره امتحان کن.';
const BANNED_TEXT = '⊘ دسترسی شما توسط مدیر محدود شده است.';
const UNBANNED_TEXT = '✓ دسترسی شما دوباره فعال شد.';
const LIMIT_REACHED_TEXT = '⊘ سقف پیام امروز تو تمام شد. فردا دوباره امتحان کن.';
const NOT_ADMIN_TEXT = '⊘ این بخش فقط برای مدیر است.';
const CLEARED_BY_ADMIN_TEXT = '✖ گفتگوی شما توسط مدیر پاک شد.';
const ADMIN_MAIN_TEXT = "✪ پنل مدیریت ربات\nیکی از گزینه‌ها را انتخاب کن:";
const ADMIN_BROADCAST_ASK = "✉ متن پیام همگانی را بفرست.\nبرای لغو دکمه‌ی انصراف را بزن.";
const ADMIN_CLOSED_TEXT = '✖ پنل بسته شد. برای باز کردن دوباره، /admin را بفرست.';

// ============================================================
// ذخیره‌سازی (فایل JSON با قفل)
// ============================================================
function tx(callable $fn, bool $write = true): mixed
{
    if (!is_dir(STORAGE_DIR)) {
        mkdir(STORAGE_DIR, 0775, true);
    }
    $path = STORAGE_DIR . '/storage.json';
    $lock = fopen(STORAGE_DIR . '/.lock', 'c');
    flock($lock, $write ? LOCK_EX : LOCK_SH);
    try {
        $data = [];
        if (is_file($path)) {
            $decoded = json_decode((string) file_get_contents($path), true);
            $data = is_array($decoded) ? $decoded : [];
        }
        $result = $fn($data);
        if ($write) {
            $json = (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
            file_put_contents($path . '.tmp', $json);
            rename($path . '.tmp', $path);
        }
        return $result;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function kvGet(string $key, mixed $default = null): mixed
{
    return tx(static fn(array $d) => $d[$key] ?? $default, false);
}

function kvSet(string $key, mixed $value): void
{
    tx(static function (array &$d) use ($key, $value): void {
        $d[$key] = $value;
    });
}

function kvDelete(string $key): void
{
    tx(static function (array &$d) use ($key): void {
        unset($d[$key]);
    });
}

function kvArray(string $key): array
{
    $value = kvGet($key, []);
    return is_array($value) ? $value : [];
}

function trackMessage(int $chatId, mixed $messageId): void
{
    $id = (int) $messageId;
    if ($id <= 0) {
        return;
    }
    tx(static function (array &$d) use ($chatId, $id): void {
        $key = 'msgs_' . $chatId;
        $ids = $d[$key] ?? [];
        $ids[] = $id;
        $d[$key] = array_slice($ids, -MAX_TRACKED_MESSAGES);
    });
}

function popTrackedMessages(int $chatId): array
{
    return tx(static function (array &$d) use ($chatId): array {
        $key = 'msgs_' . $chatId;
        $ids = $d[$key] ?? [];
        $d[$key] = [];
        return is_array($ids) ? $ids : [];
    });
}

function getHistory(int $chatId): array
{
    return array_values(kvArray('history_' . $chatId));
}

function saveHistory(int $chatId, array $history): void
{
    kvSet('history_' . $chatId, array_slice(array_values($history), -MAX_HISTORY));
}

function clearHistory(int $chatId): void
{
    kvSet('history_' . $chatId, []);
}

function getVoice(int $chatId): string
{
    $voice = (string) kvGet('voice_name_' . $chatId, DEFAULT_VOICE);
    return in_array($voice, VOICE_OPTIONS, true) ? $voice : DEFAULT_VOICE;
}

function isVoiceOn(int $chatId): bool
{
    return (bool) kvGet('voice_on_' . $chatId, false);
}

function getMode(int $chatId): ?array
{
    $mode = kvGet('mode_' . $chatId);
    return is_array($mode) ? $mode : null;
}

function setMode(int $chatId, string $mode, array $extra = []): void
{
    kvSet('mode_' . $chatId, ['mode' => $mode] + $extra);
}

function clearMode(int $chatId): void
{
    kvDelete('mode_' . $chatId);
}

// ----- کاربران، مسدودی‌ها و سقف روزانه -----
function updateUser(int $chatId, array $from): void
{
    $now = date('Y-m-d H:i');
    $firstName = trim((string) ($from['first_name'] ?? ''));
    $username = trim((string) ($from['username'] ?? ''));
    tx(static function (array &$d) use ($chatId, $firstName, $username, $now): void {
        $key = (string) $chatId;
        $u = $d['users'][$key] ?? [];
        if ($firstName !== '') {
            $u['name'] = $firstName;
        }
        if ($username !== '') {
            $u['username'] = $username;
        }
        $u['last_seen'] = $now;
        $u['messages'] = (int) ($u['messages'] ?? 0) + 1;
        $u['joined'] = $u['joined'] ?? $now;
        $d['users'][$key] = $u;
    });
}

function getDailyLimit(): int
{
    $value = kvGet('daily_limit');
    return $value === null ? DEFAULT_DAILY_LIMIT : (int) $value;
}

function withinQuota(int $chatId): bool
{
    $limit = getDailyLimit();
    if ($limit <= 0) {
        return true;
    }
    return tx(static function (array &$d) use ($chatId, $limit): bool {
        $key = (string) $chatId;
        $u = $d['users'][$key] ?? [];
        $today = date('Y-m-d');
        if (($u['day'] ?? '') !== $today) {
            $u['day'] = $today;
            $u['day_count'] = 0;
        }
        $allowed = (int) ($u['day_count'] ?? 0) < $limit;
        if ($allowed) {
            $u['day_count'] = (int) ($u['day_count'] ?? 0) + 1;
        }
        $d['users'][$key] = $u;
        return $allowed;
    });
}

function claimUpdate(int $updateId): bool
{
    return tx(static function (array &$d) use ($updateId): bool {
        $seen = $d['seen_updates'] ?? [];
        if (in_array($updateId, $seen, true)) {
            return false;
        }
        $seen[] = $updateId;
        $d['seen_updates'] = array_slice($seen, -500);
        return true;
    });
}

function adminIds(): array
{
    static $ids = null;
    if ($ids === null) {
        $ids = [];
        foreach (preg_split('/[,;\s]+/', ADMIN_IDS_RAW) ?: [] as $part) {
            if (preg_match('/^-?\d+$/', $part)) {
                $ids[] = (int) $part;
            }
        }
    }
    return $ids;
}

function isAdmin(int $chatId): bool
{
    return in_array($chatId, adminIds(), true);
}

function isBanned(int $chatId): bool
{
    return isset(kvArray('banned')[(string) $chatId]);
}

function setBanned(int $uid, bool $flag): bool
{
    if ($uid <= 0 || isAdmin($uid)) {
        return false;
    }
    tx(static function (array &$d) use ($uid, $flag): void {
        if ($flag) {
            $d['banned'][(string) $uid] = true;
        } else {
            unset($d['banned'][(string) $uid]);
        }
    });
    notify($uid, $flag ? BANNED_TEXT : UNBANNED_TEXT);
    return true;
}

function clearUser(int $uid): void
{
    if ($uid <= 0) {
        return;
    }
    clearHistory($uid);
    clearMode($uid);
    deleteMessages($uid, popTrackedMessages($uid));
    notify($uid, CLEARED_BY_ADMIN_TEXT);
}

// ============================================================
// ارتباط با API تلگرام
// ============================================================
function tg(string $method, array $params = []): array
{
    foreach ($params as $k => $v) {
        if (is_array($v)) {
            $params[$k] = json_encode($v, JSON_UNESCAPED_UNICODE);
        }
    }
    $data = [];
    for ($attempt = 0; $attempt < 3; $attempt++) {
        $ch = curl_init(API . $method);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $params,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 40,
        ]);
        $res = curl_exec($ch);
        curl_close($ch);

        $data = json_decode((string) $res, true);
        $data = is_array($data) ? $data : ['ok' => false, 'description' => 'no response'];
        if (($data['ok'] ?? false) === true) {
            return $data;
        }
        if (($data['error_code'] ?? 0) !== 429) {
            break;
        }
        sleep((int) ($data['parameters']['retry_after'] ?? 3) + 1);
    }
    return $data;
}

function notify(int $chatId, string $text): void
{
    tg('sendMessage', ['chat_id' => $chatId, 'text' => $text]);
}

function sendText(int $chatId, string $text, ?array $keyboard = null, ?string $parseMode = null): ?int
{
    $params = ['chat_id' => $chatId, 'text' => $text];
    if ($parseMode !== null) {
        $params['parse_mode'] = $parseMode;
    }
    if ($keyboard !== null) {
        $params['reply_markup'] = $keyboard;
    }
    $res = tg('sendMessage', $params);
    $mid = isset($res['result']['message_id']) ? (int) $res['result']['message_id'] : null;
    trackMessage($chatId, $mid);
    return $mid;
}

function editText(int $chatId, int $messageId, string $text, ?string $parseMode = null, ?array $keyboard = null): bool
{
    $params = ['chat_id' => $chatId, 'message_id' => $messageId, 'text' => $text];
    if ($parseMode !== null) {
        $params['parse_mode'] = $parseMode;
    }
    if ($keyboard !== null) {
        $params['reply_markup'] = $keyboard;
    }
    $res = tg('editMessageText', $params);
    if (($res['ok'] ?? false) === true) {
        return true;
    }
    return str_contains((string) ($res['description'] ?? ''), 'not modified');
}

function deleteMessages(int $chatId, array $messageIds): void
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $messageIds))));
    foreach (array_chunk($ids, 100) as $batch) {
        $res = tg('deleteMessages', ['chat_id' => $chatId, 'message_ids' => $batch]);
        if (($res['ok'] ?? false) === true) {
            continue;
        }
        foreach ($batch as $mid) {
            tg('deleteMessage', ['chat_id' => $chatId, 'message_id' => $mid]);
            usleep(50000);
        }
    }
}

function sendPhoto(int $chatId, string $bytes, string $caption = ''): bool
{
    $params = ['chat_id' => $chatId, 'photo' => new CURLStringFile($bytes, 'image.jpg', 'image/jpeg')];
    if ($caption !== '') {
        $params['caption'] = mb_substr($caption, 0, 1000);
    }
    $res = tg('sendPhoto', $params);
    trackMessage($chatId, $res['result']['message_id'] ?? null);
    return ($res['ok'] ?? false) === true;
}

function sendVoice(int $chatId, string $bytes): bool
{
    $res = tg('sendVoice', [
        'chat_id' => $chatId,
        'voice'   => new CURLStringFile($bytes, 'reply.mp3', 'audio/mpeg'),
    ]);
    trackMessage($chatId, $res['result']['message_id'] ?? null);
    return ($res['ok'] ?? false) === true;
}

function answerCallback(string $callbackId, string $text = ''): void
{
    $params = ['callback_query_id' => $callbackId];
    if ($text !== '') {
        $params['text'] = $text;
    }
    tg('answerCallbackQuery', $params);
}

function getIncomingImageUrl(array $m): ?string
{
    $fileId = null;
    if (!empty($m['photo']) && is_array($m['photo'])) {
        $last = $m['photo'][array_key_last($m['photo'])];
        $fileId = $last['file_id'] ?? null;
    } else {
        $doc = is_array($m['document'] ?? null) ? $m['document'] : [];
        if (str_starts_with((string) ($doc['mime_type'] ?? ''), 'image/')) {
            $fileId = $doc['file_id'] ?? null;
        }
    }
    if (!is_string($fileId) || $fileId === '') {
        return null;
    }
    $res = tg('getFile', ['file_id' => $fileId]);
    $path = $res['result']['file_path'] ?? null;
    return (is_string($path) && $path !== '') ? FILE_API . $path : null;
}

// ============================================================
// کیبورد و دکمه‌ها
// ============================================================
function buttonKey(string $text): string
{
    $out = preg_replace('/^[^\p{L}\p{N}]+/u', '', trim($text)) ?? '';
    return trim($out);
}

function isButton(string $text): bool
{
    if ($text === '') {
        return false;
    }
    if ($text === '/start' || $text === '/admin') {
        return true;
    }
    $key = buttonKey($text);
    return in_array($key, BUTTON_KEYS, true) || in_array($key, VOICE_OPTIONS, true);
}

function makeButton(string $label): array
{
    $symbol = BUTTON_SYMBOL[$label] ?? (in_array($label, VOICE_OPTIONS, true) ? VOICE_SYMBOL : '•');
    $button = ['text' => $symbol . ' ' . $label];
    if (isset(BUTTON_STYLES[$label])) {
        $button['style'] = BUTTON_STYLES[$label];
    }
    return $button;
}

function replyKeyboard(array $rows): array
{
    $keyboard = [];
    foreach ($rows as $row) {
        $keyboard[] = array_map('makeButton', $row);
    }
    return ['keyboard' => $keyboard, 'resize_keyboard' => true, 'one_time_keyboard' => false];
}

function mainKeyboard(): array
{
    $row1 = EDIT_ENABLED ? [BTN_IMAGE, BTN_EDIT] : [BTN_IMAGE];
    return replyKeyboard([$row1, [BTN_VOICE, BTN_VOICE_SETTINGS], [BTN_CLEAR, BTN_HELP]]);
}

function cancelKeyboard(): array
{
    return replyKeyboard([[BTN_CANCEL]]);
}

function voiceKeyboard(): array
{
    $rows = array_chunk(VOICE_OPTIONS, 2);
    $rows[] = [BTN_BACK];
    return replyKeyboard($rows);
}

function ibtn(string $text, string $data, ?string $style = null): array
{
    $button = ['text' => $text, 'callback_data' => $data];
    if ($style !== null) {
        $button['style'] = $style;
    }
    return $button;
}

// ============================================================
// نوار پیشرفت
// ============================================================
function progressBar(int $percent, int $width = PROGRESS_BAR_WIDTH): string
{
    $percent = max(0, min(100, $percent));
    $filled = (int) round($width * $percent / 100);
    return str_repeat('▰', $filled) . str_repeat('▱', $width - $filled) . '  ' . $percent . '٪';
}

final class Progress
{
    private ?int $messageId = null;
    private int $percent = 0;
    private int $tick = 0;
    private float $lastTick = 0.0;

    public function __construct(private int $chatId, private string $label)
    {
    }

    public function start(): self
    {
        tg('sendChatAction', ['chat_id' => $this->chatId, 'action' => 'typing']);
        $this->messageId = sendText($this->chatId, $this->text());
        $this->lastTick = microtime(true);
        return $this;
    }

    public function pulse(): void
    {
        if ($this->messageId === null) {
            return;
        }
        $now = microtime(true);
        if ($now - $this->lastTick < PROGRESS_INTERVAL) {
            return;
        }
        $this->lastTick = $now;
        $step = $this->percent < 60 ? random_int(3, 8) : random_int(1, 3);
        $this->percent = min(PROGRESS_MAX_BEFORE_DONE, $this->percent + $step);
        $this->tick++;
        editText($this->chatId, $this->messageId, $this->text());
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
        $this->percent = max($this->percent, 10);
    }

    public function finish(string $text, ?string $parseMode = null): bool
    {
        if ($this->messageId !== null && editText($this->chatId, $this->messageId, $text, $parseMode)) {
            return true;
        }
        return sendText($this->chatId, $text, null, $parseMode) !== null;
    }

    private function text(): string
    {
        return SPINNER[$this->tick % count(SPINNER)] . ' ' . $this->label . "\n" . progressBar($this->percent);
    }
}

// ============================================================
// درخواست HTTP
// ============================================================
function httpFetch(string $url, array $headers = [], ?string $body = null, int $timeout = 120, ?Progress $progress = null): array
{
    $ch = curl_init($url);
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_HTTPHEADER     => $headers,
    ];
    if ($body !== null) {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = $body;
    }
    curl_setopt_array($ch, $options);

    if ($progress === null) {
        $content = curl_exec($ch);
    } else {
        $mh = curl_multi_init();
        curl_multi_add_handle($mh, $ch);
        $running = 1;
        do {
            curl_multi_exec($mh, $running);
            if ($running > 0 && curl_multi_select($mh, 0.2) === -1) {
                usleep(100000);
            }
            $progress->pulse();
        } while ($running > 0);
        $content = curl_multi_getcontent($ch);
        curl_multi_remove_handle($mh, $ch);
        curl_multi_close($mh);
    }

    $result = [
        'status' => (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE),
        'type'   => (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE),
        'body'   => is_string($content) ? $content : '',
        'error'  => curl_error($ch),
    ];
    curl_close($ch);
    return $result;
}

function authHeader(): string
{
    return 'Authorization: Bearer ' . POLLINATIONS_API_KEY;
}

// ============================================================
// هوش مصنوعی (چت)
// ============================================================
function aiChat(array $messages, ?Progress $progress = null): ?string
{
    if (POLLINATIONS_API_KEY === '') {
        return null;
    }
    $res = httpFetch(
        CHAT_URL,
        ['Content-Type: application/json', authHeader()],
        (string) json_encode(['model' => CHAT_MODEL, 'messages' => $messages], JSON_UNESCAPED_UNICODE),
        60,
        $progress
    );
    $data = json_decode($res['body'], true);
    $content = is_array($data) ? ($data['choices'][0]['message']['content'] ?? null) : null;
    if (!is_string($content) || trim($content) === '') {
        error_log('خطا در هوش مصنوعی: ' . $res['status'] . ' ' . mb_substr($res['body'], 0, 300) . ' ' . $res['error']);
        return null;
    }
    return trim($content);
}

function askAi(int $chatId, string $text, Progress $progress): string
{
    if (POLLINATIONS_API_KEY === '') {
        error_log('POLLINATIONS_API_KEY تنظیم نشده است.');
        return 'کلید هوش مصنوعی روی سرور تنظیم نشده است.';
    }

    $history = getHistory($chatId);
    $messages = array_merge(
        [['role' => 'system', 'content' => SYSTEM_PROMPT]],
        $history,
        [['role' => 'user', 'content' => $text]]
    );

    $answer = aiChat($messages, $progress);
    if ($answer === null) {
        return AI_REPLY_ERROR;
    }

    $answer = mb_substr($answer, 0, MAX_ANSWER_CHARS);
    $history[] = ['role' => 'user', 'content' => $text];
    $history[] = ['role' => 'assistant', 'content' => $answer];
    saveHistory($chatId, $history);
    return $answer;
}

function hasPersian(string $text): bool
{
    return preg_match('/[\x{0600}-\x{06FF}]/u', $text) === 1;
}

function prepareImagePrompt(string $text, Progress $progress): string
{
    if (!hasPersian($text)) {
        return $text;
    }
    $english = aiChat([
        ['role' => 'system', 'content' => IMAGE_PROMPT_INSTRUCTION],
        ['role' => 'user', 'content' => $text],
    ], $progress);
    return $english ?? $text;
}

// ============================================================
// تولید محتوا: عکس، ویرایش عکس، صدا
// ============================================================
function pathEncode(string $text): string
{
    return str_replace('%2F', '/', rawurlencode($text));
}

function fetchBinary(string $url, string $expectedType, int $timeout, Progress $progress): ?string
{
    if (POLLINATIONS_API_KEY === '') {
        return null;
    }
    $res = httpFetch($url, [authHeader()], null, $timeout, $progress);
    $ok = $res['status'] >= 200 && $res['status'] < 300
        && str_starts_with($res['type'], $expectedType)
        && $res['body'] !== '';
    if (!$ok) {
        error_log('پاسخ نامعتبر از Pollinations: ' . $res['status'] . ' ' . $res['type'] . ' ' . mb_substr($res['body'], 0, 300));
        return null;
    }
    return $res['body'];
}

function generateImage(string $prompt, Progress $progress): ?string
{
    $english = prepareImagePrompt($prompt, $progress);
    $url = IMAGE_URL . pathEncode($english) . '?' . http_build_query([
        'model'  => IMAGE_MODEL,
        'width'  => IMAGE_SIZE,
        'height' => IMAGE_SIZE,
        'nologo' => 'true',
    ]);
    return fetchBinary($url, 'image/', 120, $progress);
}

function editImage(string $prompt, string $imageUrl, Progress $progress): ?string
{
    if ($imageUrl === '') {
        return null;
    }
    $english = prepareImagePrompt($prompt, $progress);
    $url = IMAGE_URL . pathEncode($english) . '?' . http_build_query([
        'model'  => EDIT_MODEL,
        'image'  => $imageUrl,
        'width'  => IMAGE_SIZE,
        'height' => IMAGE_SIZE,
        'nologo' => 'true',
    ]);
    return fetchBinary($url, 'image/', 180, $progress);
}

function cleanForSpeech(string $text): string
{
    $speech = str_replace(['```', '/', '\\', '?', '#', '&', '%'], ' ', $text);
    $speech = (string) preg_replace('/\s+/u', ' ', trim($speech));
    return mb_substr($speech, 0, MAX_SPEECH_CHARS);
}

function generateSpeech(string $text, string $voice, Progress $progress): ?string
{
    if (POLLINATIONS_API_KEY === '') {
        error_log('ویس: کلید Pollinations تنظیم نشده است.');
        return null;
    }
    $speech = cleanForSpeech($text);
    if ($speech === '') {
        return null;
    }
    $url = AUDIO_URL . rawurlencode($speech) . '?' . http_build_query(['voice' => $voice]);
    $res = httpFetch($url, [authHeader()], null, 120, $progress);
    $type = $res['type'];
    if ($res['status'] !== 200 || $res['body'] === ''
        || str_starts_with($type, 'application/json') || str_starts_with($type, 'text/')) {
        error_log('ویس: پاسخ نامعتبر، کد: ' . $res['status'] . ' نوع: ' . $type . ' متن: ' . mb_substr($res['body'], 0, 300));
        return null;
    }
    return $res['body'];
}

function runMediaJob(int $chatId, string $label, string $doneText, string $errorText, callable $build, string $caption = ''): void
{
    $progress = (new Progress($chatId, $label))->start();
    $bytes = $build($progress);
    if ($bytes === null) {
        $progress->finish($errorText);
        return;
    }
    if (!sendPhoto($chatId, $bytes, mb_substr($caption, 0, 200))) {
        $progress->finish($errorText);
        return;
    }
    $progress->finish($doneText);
}

// ============================================================
// قالب‌بندی پاسخ (HTML: کد و نقل‌قول)
// ============================================================
function escHtml(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function formatAnswer(string $text): string
{
    $parts = [];
    $codeLines = [];
    $textLines = [];
    $inCode = false;

    foreach (explode("\n", $text) as $line) {
        if (str_starts_with(trim($line), '```')) {
            if ($inCode) {
                $parts[] = '<pre>' . escHtml(implode("\n", $codeLines)) . '</pre>';
                $codeLines = [];
                $inCode = false;
            } else {
                if ($textLines) {
                    $parts[] = '<blockquote>' . escHtml(implode("\n", $textLines)) . '</blockquote>';
                    $textLines = [];
                }
                $inCode = true;
            }
            continue;
        }
        if ($inCode) {
            $codeLines[] = $line;
        } else {
            $textLines[] = $line;
        }
    }
    if ($inCode && $codeLines) {
        $parts[] = '<pre>' . escHtml(implode("\n", $codeLines)) . '</pre>';
    }
    if ($textLines) {
        $parts[] = '<blockquote>' . escHtml(implode("\n", $textLines)) . '</blockquote>';
    }
    return $parts ? implode("\n", $parts) : escHtml($text);
}

function deliverAnswer(Progress $progress, string $answer): void
{
    if (!$progress->finish(formatAnswer($answer), 'HTML')) {
        $progress->finish($answer);
    }
}

// ============================================================
// پنل مدیریت (دکمه‌های اینلاین رنگی)
// ============================================================
function showPanel(int $chatId, ?int $mid, string $text, array $markup): void
{
    if ($mid !== null && editText($chatId, $mid, $text, null, $markup)) {
        return;
    }
    sendText($chatId, $text, $markup);
}

function showAdminMain(int $chatId, ?int $mid = null): void
{
    $markup = ['inline_keyboard' => [
        [ibtn('آمار', 'a:stats', 'primary'), ibtn('کاربران', 'a:users:0', 'primary')],
        [ibtn('پیام همگانی', 'a:bcast', 'success'), ibtn('سقف روزانه', 'a:limit', 'primary')],
        [ibtn('بستن پنل', 'a:close', 'danger')],
    ]];
    showPanel($chatId, $mid, ADMIN_MAIN_TEXT, $markup);
}

function showStats(int $chatId, ?int $mid): void
{
    $users = kvArray('users');
    $today = date('Y-m-d');
    $totalMessages = 0;
    $activeToday = 0;
    foreach ($users as $u) {
        $totalMessages += (int) ($u['messages'] ?? 0);
        if (substr((string) ($u['last_seen'] ?? ''), 0, 10) === $today) {
            $activeToday++;
        }
    }
    $limit = getDailyLimit();
    $text = sprintf(
        "✦ آمار ربات\n\n➜ کل کاربران: %d\n➜ فعال امروز: %d\n➜ مسدودشده‌ها: %d\n➜ کل پیام‌ها: %d\n➜ سقف روزانه: %s",
        count($users),
        $activeToday,
        count(kvArray('banned')),
        $totalMessages,
        $limit <= 0 ? 'نامحدود' : ($limit . ' پیام در روز')
    );
    showPanel($chatId, $mid, $text, ['inline_keyboard' => [[ibtn('بازگشت', 'a:main', 'primary')]]]);
}

function showUsers(int $chatId, ?int $mid, int $page): void
{
    $items = kvArray('users');
    $banned = kvArray('banned');
    uasort($items, static fn(array $a, array $b): int
        => strcmp((string) ($b['last_seen'] ?? ''), (string) ($a['last_seen'] ?? '')));

    $ids = array_map('strval', array_keys($items));
    $total = count($ids);
    $pages = max(1, (int) ceil($total / USERS_PER_PAGE));
    $page = max(0, min($page, $pages - 1));

    $rows = [];
    foreach (array_slice($ids, $page * USERS_PER_PAGE, USERS_PER_PAGE) as $uid) {
        $info = $items[$uid] ?? [];
        $name = (string) ($info['name'] ?? '');
        if ($name === '') {
            $name = !empty($info['username']) ? '@' . $info['username'] : $uid;
        }
        $isBanned = isset($banned[$uid]);
        $rows[] = [ibtn(
            ($isBanned ? '✖ ' : '❖ ') . mb_substr($name, 0, 28),
            'a:u:' . $uid,
            $isBanned ? 'danger' : null
        )];
    }

    $nav = [];
    if ($page > 0) {
        $nav[] = ibtn('قبلی', 'a:users:' . ($page - 1));
    }
    if ($page < $pages - 1) {
        $nav[] = ibtn('بعدی', 'a:users:' . ($page + 1));
    }
    if ($nav) {
        $rows[] = $nav;
    }
    $rows[] = [ibtn('بازگشت', 'a:main', 'primary')];

    $text = $total === 0
        ? '❖ هنوز کاربری ثبت نشده است.'
        : sprintf("❖ کاربران: %d نفر\nصفحه %d از %d", $total, $page + 1, $pages);
    showPanel($chatId, $mid, $text, ['inline_keyboard' => $rows]);
}

function showUser(int $chatId, ?int $mid, string $uid): void
{
    $users = kvArray('users');
    $info = $users[$uid] ?? null;
    if (!is_array($info)) {
        showPanel($chatId, $mid, '✖ این کاربر پیدا نشد.', ['inline_keyboard' => [
            [ibtn('لیست کاربران', 'a:users:0', 'primary')],
        ]]);
        return;
    }

    $banned = isBanned((int) $uid);
    $name = (string) ($info['name'] ?? '');
    $username = !empty($info['username']) ? '@' . $info['username'] : 'ندارد';
    $text = sprintf(
        "❖ مشخصات کاربر\n\n➜ نام: %s\n➜ یوزرنیم: %s\n➜ شناسه: %s\n➜ پیام‌ها: %s\n➜ آخرین فعالیت: %s\n➜ وضعیت: %s",
        $name !== '' ? $name : 'نامشخص',
        $username,
        $uid,
        (string) ($info['messages'] ?? 0),
        (string) ($info['last_seen'] ?? 'نامشخص'),
        $banned ? 'مسدود' : 'فعال'
    );

    $banButton = $banned
        ? ibtn('آزاد کردن', 'a:unban:' . $uid, 'success')
        : ibtn('مسدود کردن', 'a:ban:' . $uid, 'danger');
    $markup = ['inline_keyboard' => [
        [$banButton],
        [ibtn('پاک کردن گفتگو', 'a:clr:' . $uid, 'primary')],
        [ibtn('لیست کاربران', 'a:users:0', 'primary')],
    ]];
    showPanel($chatId, $mid, $text, $markup);
}

function showLimit(int $chatId, ?int $mid): void
{
    $current = getDailyLimit();
    $currentText = $current <= 0 ? 'نامحدود' : ($current . ' پیام در روز');
    $options = [[10, '۱۰ پیام'], [20, '۲۰ پیام'], [50, '۵۰ پیام'], [100, '۱۰۰ پیام'], [0, 'نامحدود']];

    $rows = [];
    $row = [];
    foreach ($options as [$value, $label]) {
        $row[] = ibtn($label, 'a:setlim:' . $value, 'primary');
        if (count($row) === 2) {
            $rows[] = $row;
            $row = [];
        }
    }
    if ($row) {
        $rows[] = $row;
    }
    $rows[] = [ibtn('بازگشت', 'a:main')];

    showPanel(
        $chatId,
        $mid,
        "❖ سقف پیام روزانه برای کاربران عادی\nفعلی: $currentText\nیکی را انتخاب کن:",
        ['inline_keyboard' => $rows]
    );
}

function doBroadcast(int $adminChatId, string $text): void
{
    $users = kvArray('users');
    $banned = kvArray('banned');
    $sent = 0;
    $failed = 0;
    foreach (array_keys($users) as $uid) {
        if (isset($banned[$uid])) {
            continue;
        }
        $res = tg('sendMessage', ['chat_id' => (int) $uid, 'text' => $text]);
        if (($res['ok'] ?? false) === true) {
            $sent++;
        } else {
            $failed++;
        }
        usleep(50000);
    }
    sendText(
        $adminChatId,
        sprintf("✓ پیام همگانی ارسال شد.\nموفق: %d\nناموفق: %d", $sent, $failed),
        mainKeyboard()
    );
}

// ============================================================
// منطق ربات
// ============================================================
function handleMessage(array $m): void
{
    $chatId = (int) ($m['chat']['id'] ?? 0);
    $from = is_array($m['from'] ?? null) ? $m['from'] : [];
    if ($chatId === 0 || !empty($from['is_bot'])) {
        return;
    }

    $text = trim((string) ($m['text'] ?? $m['caption'] ?? ''));
    $hasImage = !empty($m['photo'])
        || str_starts_with((string) ($m['document']['mime_type'] ?? ''), 'image/');
    if ($text === '' && !$hasImage) {
        return;
    }

    trackMessage($chatId, $m['message_id'] ?? null);
    updateUser($chatId, $from);

    $admin = isAdmin($chatId);

    if (!$admin && isBanned($chatId)) {
        sendText($chatId, BANNED_TEXT);
        return;
    }

    if ($admin && $text !== '' && !isButton($text) && kvGet('admin_bcast_' . $chatId) !== null) {
        kvDelete('admin_bcast_' . $chatId);
        doBroadcast($chatId, $text);
        return;
    }

    if (!$admin && !isButton($text) && !withinQuota($chatId)) {
        sendText($chatId, LIMIT_REACHED_TEXT, mainKeyboard());
        return;
    }

    routeMessage($chatId, $text, $m);
}

function routeMessage(int $chatId, string $text, array $m): void
{
    if ($text === '/start') {
        clearMode($chatId);
        sendText($chatId, START_TEXT, mainKeyboard());
        return;
    }

    if ($text === '/admin') {
        if (isAdmin($chatId)) {
            clearMode($chatId);
            kvDelete('admin_bcast_' . $chatId);
            showAdminMain($chatId);
        } else {
            sendText($chatId, NOT_ADMIN_TEXT, mainKeyboard());
        }
        return;
    }

    $key = buttonKey($text);

    if ($key === BTN_HELP) {
        sendText($chatId, HELP_TEXT, mainKeyboard());
        return;
    }

    if ($key === BTN_CANCEL || $key === BTN_BACK) {
        clearMode($chatId);
        sendText($chatId, CANCEL_TEXT, mainKeyboard());
        return;
    }

    if ($key === BTN_CLEAR) {
        clearMode($chatId);
        clearHistory($chatId);
        (new Progress($chatId, LABEL_CLEAR))->start();
        deleteMessages($chatId, popTrackedMessages($chatId));
        sendText($chatId, HISTORY_CLEARED_TEXT, mainKeyboard());
        return;
    }

    if ($key === BTN_VOICE_SETTINGS) {
        clearMode($chatId);
        sendText($chatId, sprintf(VOICE_SETTINGS_TEXT, getVoice($chatId)), voiceKeyboard());
        return;
    }

    if (in_array($key, VOICE_OPTIONS, true)) {
        clearMode($chatId);
        kvSet('voice_name_' . $chatId, $key);
        sendText($chatId, sprintf(VOICE_CHANGED_TEXT, $key), mainKeyboard());
        return;
    }

    if ($key === BTN_IMAGE) {
        setMode($chatId, MODE_IMAGE);
        sendText($chatId, IMAGE_ASK_TEXT, cancelKeyboard());
        return;
    }

    if ($key === BTN_EDIT && EDIT_ENABLED) {
        setMode($chatId, MODE_EDIT_PHOTO);
        sendText($chatId, EDIT_ASK_PHOTO, cancelKeyboard());
        return;
    }

    if ($key === BTN_VOICE) {
        $on = !isVoiceOn($chatId);
        kvSet('voice_on_' . $chatId, $on);
        sendText($chatId, $on ? VOICE_ON_TEXT : VOICE_OFF_TEXT, mainKeyboard());
        return;
    }

    $state = getMode($chatId);
    if ($state !== null) {
        $mode = (string) ($state['mode'] ?? '');

        if ($mode === MODE_EDIT_PHOTO) {
            $url = getIncomingImageUrl($m);
            if ($url !== null) {
                setMode($chatId, MODE_EDIT_PROMPT, ['image_url' => $url]);
                sendText($chatId, EDIT_ASK_PROMPT, cancelKeyboard());
            } else {
                sendText($chatId, PHOTO_MISSING_TEXT, cancelKeyboard());
            }
            return;
        }

        if ($text === '') {
            return;
        }

        clearMode($chatId);

        if ($mode === MODE_EDIT_PROMPT) {
            $imageUrl = (string) ($state['image_url'] ?? '');
            runMediaJob(
                $chatId, LABEL_EDIT, EDIT_DONE_TEXT, EDIT_ERROR,
                static fn(Progress $p): ?string => editImage($text, $imageUrl, $p),
                $text
            );
            sendText($chatId, CONTINUE_TEXT, mainKeyboard());
            return;
        }

        if ($mode === MODE_IMAGE) {
            runMediaJob(
                $chatId, LABEL_IMAGE, IMAGE_DONE_TEXT, IMAGE_ERROR,
                static fn(Progress $p): ?string => generateImage($text, $p),
                $text
            );
        }
        sendText($chatId, CONTINUE_TEXT, mainKeyboard());
        return;
    }

    if ($text === '') {
        return;
    }

    $progress = (new Progress($chatId, LABEL_CHAT))->start();
    $started = microtime(true);

    $answer = askAi($chatId, $text, $progress);

    $elapsed = microtime(true) - $started;
    if ($elapsed < MIN_WAIT_SECONDS) {
        usleep((int) ((MIN_WAIT_SECONDS - $elapsed) * 1000000));
    }

    if (isVoiceOn($chatId)) {
        $progress->setLabel(LABEL_VOICE);
        $audio = generateSpeech($answer, getVoice($chatId), $progress);
        if ($audio !== null && sendVoice($chatId, $audio)) {
            $progress->finish(VOICE_DONE_TEXT);
            return;
        }
        error_log('ساخت یا ارسال ویس ناموفق بود؛ پاسخ متنی فرستاده می‌شود.');
    }

    deliverAnswer($progress, $answer);
}

function handleCallback(array $cb): void
{
    $callbackId = (string) ($cb['id'] ?? '');
    $sender = (int) ($cb['from']['id'] ?? 0);
    $msg = is_array($cb['message'] ?? null) ? $cb['message'] : [];
    $chatId = (int) ($msg['chat']['id'] ?? 0);
    $mid = isset($msg['message_id']) ? (int) $msg['message_id'] : null;
    $data = (string) ($cb['data'] ?? '');

    if (!isAdmin($sender)) {
        answerCallback($callbackId, NOT_ADMIN_TEXT);
        return;
    }
    answerCallback($callbackId);

    $parts = explode(':', $data);
    if (count($parts) < 2 || $parts[0] !== 'a' || $chatId === 0) {
        return;
    }
    $act = $parts[1];
    $arg = $parts[2] ?? '';

    switch ($act) {
        case 'main':
            kvDelete('admin_bcast_' . $chatId);
            showAdminMain($chatId, $mid);
            break;
        case 'stats':
            showStats($chatId, $mid);
            break;
        case 'users':
            showUsers($chatId, $mid, (int) $arg);
            break;
        case 'u':
            showUser($chatId, $mid, $arg);
            break;
        case 'ban':
            setBanned((int) $arg, true);
            showUser($chatId, $mid, $arg);
            break;
        case 'unban':
            setBanned((int) $arg, false);
            showUser($chatId, $mid, $arg);
            break;
        case 'clr':
            clearUser((int) $arg);
            showUser($chatId, $mid, $arg);
            break;
        case 'bcast':
            kvSet('admin_bcast_' . $chatId, true);
            showPanel($chatId, $mid, ADMIN_BROADCAST_ASK, ['inline_keyboard' => [
                [ibtn('انصراف', 'a:main', 'danger')],
            ]]);
            break;
        case 'limit':
            showLimit($chatId, $mid);
            break;
        case 'setlim':
            if (preg_match('/^\d+$/', $arg)) {
                kvSet('daily_limit', (int) $arg);
            }
            showAdminMain($chatId, $mid);
            break;
        case 'close':
            kvDelete('admin_bcast_' . $chatId);
            showPanel($chatId, $mid, ADMIN_CLOSED_TEXT, ['inline_keyboard' => []]);
            break;
    }
}

// ============================================================
// ورودی Long Polling (به جای وب‌هوک)
// ============================================================
set_time_limit(0);

// حذف وب‌هوک قبلی (فقط یک بار لازم است، اما اینجا مطمئن می‌شویم)
tg('deleteWebhook');

$lastUpdateId = 0;

echo "Starting Long Polling loop...\n";

while (true) {
    $updates = tg('getUpdates', [
        'offset' => $lastUpdateId + 1,
        'timeout' => 30,
        'allowed_updates' => ['message', 'callback_query']
    ]);

    if (isset($updates['result']) && is_array($updates['result'])) {
        foreach ($updates['result'] as $update) {
            $lastUpdateId = $update['update_id'];

            try {
                if (isset($update['callback_query'])) {
                    handleCallback($update['callback_query']);
                } elseif (isset($update['message'])) {
                    handleMessage($update['message']);
                }
            } catch (Throwable $e) {
                error_log('خطای داخلی: ' . $e->getMessage());
            }
        }
    }
    
    usleep(500000); 
}
