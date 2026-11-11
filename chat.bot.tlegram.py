#!/usr/bin/env python3
# -*- coding: utf-8 -*-
# ربات تلگرام + Pollinations
# - گفتگوی فارسی‌محور با حافظه (storage.json)
# - پنل مدیریت با دستور /admin (فقط برای مدیر): آمار، کاربران، مسدود/آزاد، پاک کردن گفتگو، پیام همگانی، سقف روزانه
# - دکمه‌های رنگی کیبورد و پنل (style: primary / success / danger؛ نیاز به Bot API 9.4)
# - نوار پیشرفت، عکس، ویرایش عکس، ویس، حذف تاریخچه
# - نمادهای متنی بدون ایموجی
# - سرور keep-alive برای نگه داشتن سرویس روی Render

import os
import sys
import json
import time
import html
import random
import threading
from datetime import datetime
from urllib.parse import quote
from http.server import BaseHTTPRequestHandler, HTTPServer

import requests


# ============================================================
# تنظیمات (همه از Environment Variables خوانده می‌شوند)
# ============================================================
BOT_TOKEN = os.environ.get("BOT_TOKEN", "9204950570:AAFzycx2TKuxm3kLWO8NCN7efK4xDEbEzRE")

# شناسه‌ی عددی مدیر(ها) در تلگرام. چند نفر را با کاما جدا کن: 123,456
ADMIN_IDS_RAW = os.environ.get("ADMIN_ID", "8866404328")
# سقف پیام روزانه‌ی پیش‌فرض برای کاربران عادی؛ 0 یعنی نامحدود
DEFAULT_DAILY_LIMIT = int(os.environ.get("DAILY_LIMIT", "50") or 0)

POLLINATIONS_API_KEY = os.environ.get("POLLINATIONS_API_KEY", "sk_bJg5A28imMHYUBIGJU23NYuqaRUQsyku")
POLLINATIONS_CHAT_URL = "https://gen.pollinations.ai/v1/chat/completions"
CHAT_MODEL = "openai/gpt-5.4-nano"
SYSTEM_PROMPT = (
    "You are a friendly, helpful assistant. Your users are mostly Persian (Farsi) speakers. "
    "By default, reply in Persian. If the user writes in English, reply in English. "
    "Keep the same language as the user's latest message."
)
IMAGE_PROMPT_INSTRUCTION = (
    "Translate the user's image request into one detailed English prompt for a text-to-image model. "
    "Describe subject, style, lighting and composition. Output only the prompt, no quotes, no explanation."
)
MAX_HISTORY = 20
MAX_ANSWER_CHARS = 4000
MAX_TRACKED_MESSAGES = 1000

POLLINATIONS_IMAGE_URL = "https://gen.pollinations.ai/image/"
IMAGE_MODEL = "flux"
EDIT_MODEL = "kontext"          # ممکن است نیاز به سطح پولی داشته باشد
IMAGE_WIDTH = 1024
IMAGE_HEIGHT = 1024

POLLINATIONS_AUDIO_URL = "https://gen.pollinations.ai/audio/"
DEFAULT_VOICE = "alloy"
VOICE_OPTIONS = ["alloy", "nova", "onyx", "shimmer", "echo", "fable"]
MAX_SPEECH_CHARS = 800

# اگر False باشد، دکمه‌ی ویرایش عکس نشان داده نمی‌شود.
EDIT_ENABLED = True

TG_API = "https://api.telegram.org/bot%s/"
TG_FILE = "https://api.telegram.org/file/bot%s/"
STORAGE_FILE = os.path.join(os.path.dirname(os.path.abspath(__file__)), "storage.json")

# ----- نوار پیشرفت -----
PROGRESS_INTERVAL = 1.4
PROGRESS_MAX_BEFORE_DONE = 92
PROGRESS_BAR_WIDTH = 12
SPINNER = ["◐", "◓", "◑", "◒"]
MIN_WAIT_SECONDS = 1.5

LABEL_CHAT = "✧ در حال فکر کردن"
LABEL_VOICE = "♫ در حال ساخت ویس"
LABEL_IMAGE = "✦ در حال ساخت عکس"
LABEL_EDIT = "✎ در حال ویرایش عکس"
LABEL_CLEAR = "✖ در حال پاک کردن گفتگو"

# ----- دکمه‌های کیبورد اصلی -----
BTN_IMAGE = "ساخت عکس"
BTN_EDIT = "ویرایش عکس"
BTN_VOICE = "پاسخ با ویس"
BTN_VOICE_SETTINGS = "تنظیمات صدا"
BTN_CLEAR = "حذف تاریخچه"
BTN_HELP = "راهنما"
BTN_CANCEL = "انصراف"
BTN_BACK = "بازگشت"

BUTTON_SYMBOL = {
    BTN_IMAGE: "✦",
    BTN_EDIT: "✎",
    BTN_VOICE: "♫",
    BTN_VOICE_SETTINGS: "❖",
    BTN_CLEAR: "✖",
    BTN_HELP: "✿",
    BTN_CANCEL: "⊘",
    BTN_BACK: "➜",
}
VOICE_SYMBOL = "♪"

BUTTON_STYLES = {
    BTN_IMAGE: "primary",
    BTN_EDIT: "primary",
    BTN_VOICE: "success",
    BTN_VOICE_SETTINGS: "primary",
    BTN_HELP: "primary",
    BTN_CLEAR: "danger",
    BTN_CANCEL: "danger",
}

BUTTON_KEYS = {BTN_IMAGE, BTN_EDIT, BTN_VOICE, BTN_VOICE_SETTINGS, BTN_CLEAR,
               BTN_HELP, BTN_CANCEL, BTN_BACK}

PROMO = "✦ ربات‌ها و کانال ما: @NabAfzar"

START_TEXT = (
    "✦ سلام! خوش اومدی.\n"
    "➜ هر سوالی داری بپرس؛ فارسی یا انگلیسی. گفتگوی قبلی‌ات هم ذخیره می‌شه.\n\n"
    "✦ عکس بساز، ✎ عکس بفرست و ویرایشش کن، ♫ حالت ویس را روشن کن یا ❖ صدایش را عوض کن.\n\n"
    + PROMO
)
HELP_TEXT = (
    "✿ راهنمای ربات\n\n"
    "➜ گفتگو: هر متنی بفرستی جواب می‌دهم. به فارسی بنویس یا انگلیسی.\n"
    "✦ ساخت عکس: دکمه را بزن و توضیح عکس را بفرست (فارسی یا انگلیسی).\n"
    "✎ ویرایش عکس: دکمه را بزن، یک عکس بفرست، بعد بگو چه تغییری بدهم.\n"
    "♫ پاسخ با ویس: روشن یا خاموش کردن پاسخ صوتی.\n"
    "❖ تنظیمات صدا: انتخاب صدای ویس.\n"
    "✖ حذف تاریخچه: پاک کردن گفتگو و همه‌ی پیام‌های این چت.\n"
    "⊘ انصراف: لغو کار جاری.\n\n"
    + PROMO
)

IMAGE_ASK_TEXT = "✦ توضیح عکسی که می‌خواهی را بفرست.\nمثلاً: یک گربه روی ماه با لباس فضانوردی"
EDIT_ASK_PHOTO = "➜ یک عکس بفرست تا ویرایشش کنم."
EDIT_ASK_PROMPT = "✎ بگو چه تغییری روی عکس بدهم.\nمثلاً: پس‌زمینه را به ساحل تغییر بده"
VOICE_SETTINGS_TEXT = "❖ صدای فعلی: %s\nیکی از صداهای زیر را انتخاب کن:"
VOICE_CHANGED_TEXT = "✓ صدای ویس به %s تغییر کرد."

IMAGE_DONE_TEXT = "✓ عکس شما آماده است"
EDIT_DONE_TEXT = "✓ عکس ویرایش شد"
VOICE_DONE_TEXT = "♫ پاسخ به‌صورت ویس ارسال شد."
IMAGE_ERROR = "✖ متأسفم، ساخت عکس انجام نشد. کمی بعد دوباره امتحان کن."
EDIT_ERROR = "✖ متأسفم، ویرایش عکس انجام نشد. مطمئن شو عکس را درست فرستادی."

VOICE_ON_TEXT = "♫ حالت ویس روشن شد. از این به بعد پاسخ‌ها به‌صورت ویس ارسال می‌شوند."
VOICE_OFF_TEXT = "✎ حالت ویس خاموش شد. از این به بعد پاسخ‌ها متنی هستند."
HISTORY_CLEARED_TEXT = "✓ گفتگو و پیام‌های این چت پاک شد. از این به بعد از صفر شروع می‌کنیم."
CANCEL_TEXT = "⊘ لغو شد."
AI_REPLY_ERROR = "✖ متأسفم، الان نتونستم جواب بدم. کمی بعد دوباره امتحان کن."

BANNED_TEXT = "⊘ دسترسی شما توسط مدیر محدود شده است."
UNBANNED_TEXT = "✓ دسترسی شما دوباره فعال شد."
LIMIT_REACHED_TEXT = "⊘ سقف پیام امروز تو تمام شد. فردا دوباره امتحان کن."
NOT_ADMIN_TEXT = "⊘ این بخش فقط برای مدیر است."
CLEARED_BY_ADMIN_TEXT = "✖ گفتگوی شما توسط مدیر پاک شد."

ADMIN_MAIN_TEXT = "✪ پنل مدیریت ربات\nیکی از گزینه‌ها را انتخاب کن:"
ADMIN_BROADCAST_ASK = "✉ متن پیام همگانی را بفرست.\nبرای لغو دکمه‌ی انصراف را بزن."
ADMIN_CLOSED_TEXT = "✖ پنل بسته شد. برای باز کردن دوباره، /admin را بفرست."

USERS_PER_PAGE = 8
DEBUG = False


# ============================================================
# سرور keep-alive برای Render
# ============================================================
class _PingHandler(BaseHTTPRequestHandler):
    def _ok(self):
        self.send_response(200)
        self.send_header("Content-Type", "text/plain; charset=utf-8")
        self.end_headers()
        if self.command != "HEAD":
            self.wfile.write(b"ok")

    def do_GET(self):
        self._ok()

    def do_HEAD(self):
        self._ok()

    def log_message(self, *args):
        pass  # لاگ درخواست‌های پینگ را چاپ نکن


def start_keepalive_server():
    port = int(os.environ.get("PORT", "10000"))  # Render این را خودش تنظیم می‌کند
    server = HTTPServer(("0.0.0.0", port), _PingHandler)
    threading.Thread(target=server.serve_forever, daemon=True).start()
    print("سرور keep-alive روی پورت %d اجرا شد." % port)


# ============================================================
# ذخیره‌سازی پایدار (storage.json)
# ============================================================
_LOCK = threading.Lock()


def _load_all():
    if not os.path.exists(STORAGE_FILE):
        return {}
    try:
        with open(STORAGE_FILE, encoding="utf-8") as f:
            data = json.load(f)
        return data if isinstance(data, dict) else {}
    except (ValueError, OSError):
        return {}


def _save_all(data):
    tmp = STORAGE_FILE + ".tmp"
    with open(tmp, "w", encoding="utf-8") as f:
        json.dump(data, f, ensure_ascii=False, indent=2)
    os.replace(tmp, STORAGE_FILE)


def kv_get(name, default=None):
    with _LOCK:
        return _load_all().get(name, default)


def kv_set(name, value):
    with _LOCK:
        data = _load_all()
        data[name] = value
        _save_all(data)


def get_history(chat_id):
    history = kv_get("history_" + str(chat_id), [])
    return history if isinstance(history, list) else []


def save_history(chat_id, history):
    kv_set("history_" + str(chat_id), history[-MAX_HISTORY:])


def clear_history(chat_id):
    kv_set("history_" + str(chat_id), [])


def track_message(chat_id, message_id):
    if not message_id:
        return
    key = "msgs_" + str(chat_id)
    ids = kv_get(key, [])
    if not isinstance(ids, list):
        ids = []
    ids.append(int(message_id))
    kv_set(key, ids[-MAX_TRACKED_MESSAGES:])


def pop_tracked_messages(chat_id):
    key = "msgs_" + str(chat_id)
    ids = kv_get(key, [])
    kv_set(key, [])
    return ids if isinstance(ids, list) else []


def get_voice_set():
    items = kv_get("voice_mode", [])
    return set(str(i) for i in items) if isinstance(items, list) else set()


def save_voice_set(voice_set):
    kv_set("voice_mode", sorted(voice_set))


def get_voice(chat_id):
    voice = kv_get("voice_name_" + str(chat_id), DEFAULT_VOICE)
    return voice if voice in VOICE_OPTIONS else DEFAULT_VOICE


def save_voice(chat_id, voice):
    kv_set("voice_name_" + str(chat_id), voice)


# ----- کاربران، مسدودی‌ها و سقف روزانه -----
def get_users():
    users = kv_get("users", {})
    return users if isinstance(users, dict) else {}


def save_users(users):
    kv_set("users", users)


def get_banned():
    items = kv_get("banned", [])
    return set(str(i) for i in items) if isinstance(items, list) else set()


def save_banned(banned):
    kv_set("banned", sorted(banned))


def get_daily_limit():
    value = kv_get("daily_limit", None)
    if value is None:
        return DEFAULT_DAILY_LIMIT
    try:
        return int(value)
    except (TypeError, ValueError):
        return DEFAULT_DAILY_LIMIT


def save_daily_limit(value):
    kv_set("daily_limit", int(value))


def parse_admin_ids(raw):
    ids = set()
    for part in raw.replace(";", ",").split(","):
        part = part.strip()
        if part.lstrip("-").isdigit():
            ids.add(int(part))
    return ids


ADMIN_IDS = parse_admin_ids(ADMIN_IDS_RAW)


def is_admin(chat_id):
    try:
        return int(chat_id) in ADMIN_IDS
    except (TypeError, ValueError):
        return False


def is_banned(chat_id):
    return str(chat_id) in get_banned()


def update_user(chat_id, sender):
    users = get_users()
    key = str(chat_id)
    u = users.get(key, {})
    u["name"] = sender.get("first_name", "") or u.get("name", "")
    u["username"] = sender.get("username", "") or u.get("username", "")
    u["last_seen"] = datetime.now().strftime("%Y-%m-%d %H:%M")
    u["messages"] = int(u.get("messages", 0)) + 1
    u.setdefault("joined", u["last_seen"])
    users[key] = u
    save_users(users)


def within_quota(chat_id):
    """اگر کاربر هنوز سقف امروزش را رد نکرده باشد True برمی‌گرداند و شمارش را یکی بالا می‌برد."""
    limit = get_daily_limit()
    if limit <= 0:
        return True
    users = get_users()
    key = str(chat_id)
    u = users.get(key, {})
    today = datetime.now().strftime("%Y-%m-%d")
    if u.get("day") != today:
        u["day"] = today
        u["day_count"] = 0
    if int(u.get("day_count", 0)) >= limit:
        users[key] = u
        save_users(users)
        return False
    u["day_count"] = int(u.get("day_count", 0)) + 1
    users[key] = u
    save_users(users)
    return True


# ============================================================
# ارتباط با API تلگرام
# ============================================================
class TgAPIError(Exception):
    pass


class TgAuthError(TgAPIError):
    pass


class TelegramBot:
    def __init__(self, token):
        self.token = token
        self.api = TG_API % token
        self.file_base = TG_FILE % token
        self.session = requests.Session()

    def call(self, method, payload=None, files=None, timeout=40, attempts=3):
        last_error = "ارتباط با تلگرام برقرار نشد."
        for attempt in range(attempts):
            try:
                if files:
                    resp = self.session.post(self.api + method, data=payload or {}, files=files, timeout=timeout)
                else:
                    resp = self.session.post(self.api + method, json=payload or {}, timeout=timeout)
            except requests.RequestException:
                time.sleep(2 * (attempt + 1))
                continue

            try:
                body = resp.json()
            except ValueError:
                body = {}

            if DEBUG:
                print("[%s] status=%s body=%s" % (method, resp.status_code, str(body)[:300]))

            if body.get("ok"):
                return body.get("result")

            code = body.get("error_code", resp.status_code)
            desc = str(body.get("description", "خطای ناشناخته از تلگرام"))
            if code == 401:
                raise TgAuthError("توکن ربات نامعتبر است.")
            if code == 429:
                retry = (body.get("parameters") or {}).get("retry_after", 3)
                time.sleep(int(retry) + 1)
                last_error = "محدودیت تلگرام؛ دوباره تلاش می‌شود."
                continue
            raise TgAPIError(desc)
        raise TgAPIError(last_error)

    def send_message(self, chat_id, text, keyboard=None, parse_mode=None):
        payload = {"chat_id": chat_id, "text": text}
        if parse_mode:
            payload["parse_mode"] = parse_mode
        if keyboard:
            payload["reply_markup"] = json.dumps(keyboard, ensure_ascii=False)
        data = self.call("sendMessage", payload)
        track_message(chat_id, (data or {}).get("message_id"))
        return data

    def edit_message(self, chat_id, message_id, text, parse_mode=None, keyboard=None):
        payload = {"chat_id": chat_id, "message_id": int(message_id), "text": text}
        if parse_mode:
            payload["parse_mode"] = parse_mode
        if keyboard is not None:
            payload["reply_markup"] = json.dumps(keyboard, ensure_ascii=False)
        try:
            return self.call("editMessageText", payload)
        except TgAPIError as err:
            if "not modified" in str(err):
                return None
            raise

    def delete_messages(self, chat_id, message_ids):
        ids = [int(i) for i in message_ids]
        for start in range(0, len(ids), 100):
            batch = ids[start:start + 100]
            try:
                self.call("deleteMessages", {"chat_id": chat_id, "message_ids": batch})
            except TgAPIError as err:
                print("deleteMessages ناموفق بود، تک‌تک حذف می‌شود:", err)
                for mid in batch:
                    try:
                        self.call("deleteMessage", {"chat_id": chat_id, "message_id": mid})
                    except TgAPIError:
                        pass
                    time.sleep(0.05)

    def send_photo(self, chat_id, data, caption=""):
        payload = {"chat_id": chat_id}
        if caption:
            payload["caption"] = caption[:1000]
        result = self.call("sendPhoto", payload, files={"photo": ("image.jpg", data, "image/jpeg")})
        track_message(chat_id, (result or {}).get("message_id"))
        return result

    def send_voice(self, chat_id, data):
        result = self.call("sendVoice", {"chat_id": chat_id},
                           files={"voice": ("reply.mp3", data, "audio/mpeg")})
        track_message(chat_id, (result or {}).get("message_id"))
        return result

    def send_chat_action(self, chat_id, action):
        try:
            self.call("sendChatAction", {"chat_id": chat_id, "action": action}, attempts=1)
        except TgAPIError:
            pass

    def answer_callback(self, callback_id, text=""):
        try:
            payload = {"callback_query_id": callback_id}
            if text:
                payload["text"] = text
            self.call("answerCallbackQuery", payload, attempts=1)
        except TgAPIError:
            pass

    def get_file_url(self, file_id):
        info = self.call("getFile", {"file_id": file_id})
        path = (info or {}).get("file_path")
        if not path:
            return None
        return self.file_base + path

    def get_updates(self, offset=None):
        payload = {"timeout": 25, "allowed_updates": ["message", "callback_query"]}
        if offset is not None:
            payload["offset"] = offset
        return self.call("getUpdates", payload, timeout=40) or []


bot = TelegramBot(BOT_TOKEN)


# ============================================================
# کیبورد معمولی (ReplyKeyboard) با رنگ دکمه
# ============================================================
def make_button(label):
    symbol = BUTTON_SYMBOL.get(label)
    if symbol is None and label in VOICE_OPTIONS:
        symbol = VOICE_SYMBOL
    if symbol is None:
        symbol = "•"
    button = {"text": symbol + " " + label}
    style = BUTTON_STYLES.get(label)
    if style:
        button["style"] = style
    return button


def make_keyboard(rows):
    return {
        "keyboard": [[make_button(label) for label in row] for row in rows],
        "resize_keyboard": True,
        "one_time_keyboard": False,
    }


def main_keyboard():
    row1 = [BTN_IMAGE] + ([BTN_EDIT] if EDIT_ENABLED else [])
    return make_keyboard([
        row1,
        [BTN_VOICE, BTN_VOICE_SETTINGS],
        [BTN_CLEAR, BTN_HELP],
    ])


def main_kb(chat_id=None):
    return main_keyboard()


CANCEL_KEYBOARD = make_keyboard([[BTN_CANCEL]])


def voice_keyboard():
    rows = [VOICE_OPTIONS[i:i + 2] for i in range(0, len(VOICE_OPTIONS), 2)]
    rows.append([BTN_BACK])
    return make_keyboard(rows)


def button_key(text):
    """ابتدای متن (نماد و فاصله) را حذف می‌کند تا دکمه‌ی انتخاب‌شده شناسایی شود."""
    out = (text or "").strip()
    while out and not (out[0].isalnum() or "\u0600" <= out[0] <= "\u06FF"):
        out = out[1:].lstrip()
    return out


def is_button(text):
    if not text:
        return False
    if text in ("/start", "/admin"):
        return True
    key = button_key(text)
    return key in BUTTON_KEYS or key in VOICE_OPTIONS


# ============================================================
# پنل مدیریت (دکمه‌های اینلاین رنگی)
# ============================================================
def ibtn(text, data, style=None):
    button = {"text": text, "callback_data": data}
    if style:
        button["style"] = style
    return button


def show_panel(chat_id, mid, text, markup):
    """اگر پیام پنل قبلاً وجود داشته باشد همان را ویرایش می‌کند، وگرنه پیام تازه می‌فرستد."""
    if mid:
        try:
            bot.edit_message(chat_id, mid, text, keyboard=markup)
            return
        except TgAPIError as err:
            print("ویرایش پنل ناموفق بود:", err)
    bot.send_message(chat_id, text, keyboard=markup)


def show_admin_main(chat_id, mid=None):
    markup = {"inline_keyboard": [
        [ibtn("آمار", "a:stats", "primary"), ibtn("کاربران", "a:users:0", "primary")],
        [ibtn("پیام همگانی", "a:bcast", "success"), ibtn("سقف روزانه", "a:limit", "primary")],
        [ibtn("بستن پنل", "a:close", "danger")],
    ]}
    show_panel(chat_id, mid, ADMIN_MAIN_TEXT, markup)


def show_stats(chat_id, mid):
    users = get_users()
    today = datetime.now().strftime("%Y-%m-%d")
    total_users = len(users)
    banned = get_banned()
    total_msgs = sum(int(u.get("messages", 0)) for u in users.values())
    active_today = sum(1 for u in users.values() if str(u.get("last_seen", ""))[:10] == today)
    limit = get_daily_limit()
    limit_text = "نامحدود" if limit <= 0 else str(limit) + " پیام در روز"
    text = (
        "✦ آمار ربات\n\n"
        "➜ کل کاربران: %d\n"
        "➜ فعال امروز: %d\n"
        "➜ مسدودشده‌ها: %d\n"
        "➜ کل پیام‌ها: %d\n"
        "➜ سقف روزانه: %s"
    ) % (total_users, active_today, len(banned), total_msgs, limit_text)
    markup = {"inline_keyboard": [[ibtn("بازگشت", "a:main", "primary")]]}
    show_panel(chat_id, mid, text, markup)


def show_users(chat_id, mid, page):
    users = get_users()
    banned = get_banned()
    items = sorted(users.items(), key=lambda kv: str(kv[1].get("last_seen", "")), reverse=True)
    total = len(items)
    pages = max(1, (total + USERS_PER_PAGE - 1) // USERS_PER_PAGE)
    page = max(0, min(page, pages - 1))
    chunk = items[page * USERS_PER_PAGE:(page + 1) * USERS_PER_PAGE]

    rows = []
    for uid, info in chunk:
        name = info.get("name") or ("@" + info["username"] if info.get("username") else uid)
        prefix = "✖ " if uid in banned else "❖ "
        style = "danger" if uid in banned else None
        rows.append([ibtn(prefix + str(name)[:28], "a:u:" + uid, style)])

    nav = []
    if page > 0:
        nav.append(ibtn("قبلی", "a:users:%d" % (page - 1)))
    if page < pages - 1:
        nav.append(ibtn("بعدی", "a:users:%d" % (page + 1)))
    if nav:
        rows.append(nav)
    rows.append([ibtn("بازگشت", "a:main", "primary")])

    text = "❖ کاربران: %d نفر\nصفحه %d از %d" % (total, page + 1, pages)
    if total == 0:
        text = "❖ هنوز کاربری ثبت نشده است."
    show_panel(chat_id, mid, text, {"inline_keyboard": rows})


def show_user(chat_id, mid, uid):
    users = get_users()
    info = users.get(uid)
    if not info:
        show_panel(chat_id, mid, "✖ این کاربر پیدا نشد.",
                   {"inline_keyboard": [[ibtn("لیست کاربران", "a:users:0", "primary")]]})
        return
    banned = uid in get_banned()
    username = ("@" + info["username"]) if info.get("username") else "ندارد"
    text = (
        "❖ مشخصات کاربر\n\n"
        "➜ نام: %s\n"
        "➜ یوزرنیم: %s\n"
        "➜ شناسه: %s\n"
        "➜ پیام‌ها: %s\n"
        "➜ آخرین فعالیت: %s\n"
        "➜ وضعیت: %s"
    ) % (
        info.get("name") or "نامشخص",
        username,
        uid,
        info.get("messages", 0),
        info.get("last_seen", "نامشخص"),
        "مسدود" if banned else "فعال",
    )
    if banned:
        ban_btn = ibtn("آزاد کردن", "a:unban:" + uid, "success")
    else:
        ban_btn = ibtn("مسدود کردن", "a:ban:" + uid, "danger")
    markup = {"inline_keyboard": [
        [ban_btn],
        [ibtn("پاک کردن گفتگو", "a:clr:" + uid, "primary")],
        [ibtn("لیست کاربران", "a:users:0", "primary")],
    ]}
    show_panel(chat_id, mid, text, markup)


def set_banned(uid, flag):
    if int(uid) in ADMIN_IDS:
        return False
    banned = get_banned()
    if flag:
        banned.add(uid)
    else:
        banned.discard(uid)
    save_banned(banned)
    try:
        bot.call("sendMessage", {"chat_id": int(uid), "text": BANNED_TEXT if flag else UNBANNED_TEXT})
    except (TgAPIError, ValueError):
        pass
    return True


def clear_user(uid):
    chat_id = int(uid)
    clear_history(chat_id)
    pending.pop(chat_id, None)
    ids = pop_tracked_messages(chat_id)
    bot.delete_messages(chat_id, ids)
    try:
        bot.call("sendMessage", {"chat_id": chat_id, "text": CLEARED_BY_ADMIN_TEXT})
    except (TgAPIError, ValueError):
        pass


def show_limit(chat_id, mid):
    current = get_daily_limit()
    current_text = "نامحدود" if current <= 0 else str(current) + " پیام در روز"
    options = [(10, "۱۰ پیام"), (20, "۲۰ پیام"), (50, "۵۰ پیام"), (100, "۱۰۰ پیام"), (0, "نامحدود")]
    rows = []
    row = []
    for value, label in options:
        row.append(ibtn(label, "a:setlim:%d" % value, "primary"))
        if len(row) == 2:
            rows.append(row)
            row = []
    if row:
        rows.append(row)
    rows.append([ibtn("بازگشت", "a:main")])
    text = "❖ سقف پیام روزانه برای کاربران عادی\nفعلی: %s\nیکی را انتخاب کن:" % current_text
    show_panel(chat_id, mid, text, {"inline_keyboard": rows})


admin_pending = {}


def do_broadcast(admin_chat_id, text):
    users = get_users()
    banned = get_banned()
    sent = 0
    failed = 0
    for uid in users:
        if uid in banned:
            continue
        try:
            bot.call("sendMessage", {"chat_id": int(uid), "text": text})
            sent += 1
        except (TgAPIError, ValueError):
            failed += 1
        time.sleep(0.05)
    bot.send_message(
        admin_chat_id,
        "✓ پیام همگانی ارسال شد.\nموفق: %d\nناموفق: %d" % (sent, failed),
        keyboard=main_kb(admin_chat_id),
    )


def handle_callback(cq):
    cb_id = cq.get("id", "")
    sender = (cq.get("from") or {}).get("id")
    msg = cq.get("message") or {}
    chat_id = (msg.get("chat") or {}).get("id")
    mid = msg.get("message_id")
    data = cq.get("data") or ""

    if not is_admin(sender):
        bot.answer_callback(cb_id, NOT_ADMIN_TEXT)
        return
    bot.answer_callback(cb_id)

    parts = data.split(":")
    if len(parts) < 2 or parts[0] != "a" or chat_id is None:
        return
    act = parts[1]
    arg = parts[2] if len(parts) > 2 else ""

    if act == "main":
        admin_pending.pop(chat_id, None)
        show_admin_main(chat_id, mid)
    elif act == "stats":
        show_stats(chat_id, mid)
    elif act == "users":
        try:
            page = int(arg or 0)
        except ValueError:
            page = 0
        show_users(chat_id, mid, page)
    elif act == "u":
        show_user(chat_id, mid, arg)
    elif act == "ban":
        set_banned(arg, True)
        show_user(chat_id, mid, arg)
    elif act == "unban":
        set_banned(arg, False)
        show_user(chat_id, mid, arg)
    elif act == "clr":
        clear_user(arg)
        show_user(chat_id, mid, arg)
    elif act == "bcast":
        admin_pending[chat_id] = {"mode": "broadcast"}
        show_panel(chat_id, mid, ADMIN_BROADCAST_ASK,
                   {"inline_keyboard": [[ibtn("انصراف", "a:main", "danger")]]})
    elif act == "limit":
        show_limit(chat_id, mid)
    elif act == "setlim":
        try:
            save_daily_limit(int(arg))
        except ValueError:
            pass
        show_admin_main(chat_id, mid)
    elif act == "close":
        admin_pending.pop(chat_id, None)
        show_panel(chat_id, mid, ADMIN_CLOSED_TEXT, {"inline_keyboard": []})


# ============================================================
# نوار پیشرفت
# ============================================================
def progress_bar(percent, width=PROGRESS_BAR_WIDTH):
    percent = max(0, min(100, int(percent)))
    filled = round(width * percent / 100)
    return "▰" * filled + "▱" * (width - filled) + "  " + str(percent) + "٪"


class Progress:
    def __init__(self, chat_id, label):
        self.chat_id = chat_id
        self.label = label
        self.percent = 0
        self.message_id = None
        self._stop = threading.Event()
        self._thread = None

    def _text(self, tick):
        return "%s %s\n%s" % (SPINNER[tick % len(SPINNER)], self.label, progress_bar(self.percent))

    def start(self):
        try:
            bot.send_chat_action(self.chat_id, "typing")
            sent = bot.send_message(self.chat_id, self._text(0))
            self.message_id = (sent or {}).get("message_id")
        except TgAPIError as err:
            print("خطا در ارسال پیام پیشرفت:", err)
            return self
        if self.message_id:
            self._thread = threading.Thread(target=self._run, daemon=True)
            self._thread.start()
        return self

    def _run(self):
        tick = 0
        while not self._stop.is_set():
            step = random.randint(3, 8) if self.percent < 60 else random.randint(1, 3)
            self.percent = min(PROGRESS_MAX_BEFORE_DONE, self.percent + step)
            try:
                bot.edit_message(self.chat_id, self.message_id, self._text(tick))
            except TgAPIError as err:
                print("به‌روزرسانی نوار پیشرفت ناموفق بود:", err)
                return
            tick += 1
            self._stop.wait(PROGRESS_INTERVAL)

    def set_label(self, label):
        self.label = label
        self.percent = max(self.percent, 10)

    def stop(self):
        self._stop.set()
        if self._thread:
            self._thread.join(timeout=5)

    def finish(self, text, parse_mode=None):
        self.stop()
        if self.message_id:
            try:
                bot.edit_message(self.chat_id, self.message_id, text, parse_mode=parse_mode)
                return
            except TgAPIError as err:
                print("ویرایش پیام نهایی ناموفق بود:", err)
        try:
            bot.send_message(self.chat_id, text, parse_mode=parse_mode)
        except TgAPIError as err:
            print("ارسال پیام نهایی ناموفق بود:", err)


# ============================================================
# قالب‌بندی متن پاسخ هوش مصنوعی (HTML: کد و نقل‌قول)
# ============================================================
def format_answer(text):
    parts = []
    code_lines = []
    text_lines = []
    in_code = False

    def flush_text():
        if text_lines:
            parts.append("<blockquote>" + html.escape("\n".join(text_lines)) + "</blockquote>")
            text_lines.clear()

    for line in text.split("\n"):
        if line.strip().startswith("```"):
            if in_code:
                parts.append("<pre>" + html.escape("\n".join(code_lines)) + "</pre>")
                code_lines.clear()
                in_code = False
            else:
                flush_text()
                in_code = True
            continue
        if in_code:
            code_lines.append(line)
        else:
            text_lines.append(line)

    if in_code and code_lines:
        parts.append("<pre>" + html.escape("\n".join(code_lines)) + "</pre>")
    flush_text()
    return "\n".join(parts) or html.escape(text)


def deliver_answer(progress, answer):
    try:
        progress.finish(format_answer(answer), parse_mode="HTML")
    except Exception as err:
        print("ارسال HTML ناموفق بود:", err)
        progress.finish(answer)


# ============================================================
# زبان
# ============================================================
def has_persian(text):
    return any("\u0600" <= ch <= "\u06FF" for ch in text)


# ============================================================
# درخواست چت به Pollinations
# ============================================================
def _chat(messages):
    if not POLLINATIONS_API_KEY:
        return None
    try:
        resp = requests.post(
            POLLINATIONS_CHAT_URL,
            json={"model": CHAT_MODEL, "messages": messages},
            headers={"Authorization": "Bearer " + POLLINATIONS_API_KEY},
            timeout=60,
        )
        data = resp.json()
        if DEBUG and "choices" not in data:
            print("پاسخ Pollinations:", resp.status_code, data)
        content = data["choices"][0]["message"]["content"]
    except (requests.RequestException, ValueError, KeyError, IndexError, TypeError) as err:
        print("خطا در هوش مصنوعی:", err)
        return None
    content = str(content or "").strip()
    return content or None


def ask_ai(chat_id, text):
    if not POLLINATIONS_API_KEY:
        print("POLLINATIONS_API_KEY تنظیم نشده است.")
        return "کلید هوش مصنوعی روی سرور تنظیم نشده است."

    history = get_history(chat_id)
    messages = [{"role": "system", "content": SYSTEM_PROMPT}] + history
    messages.append({"role": "user", "content": text})

    answer = _chat(messages)
    if not answer:
        return AI_REPLY_ERROR

    answer = answer[:MAX_ANSWER_CHARS]
    history.append({"role": "user", "content": text})
    history.append({"role": "assistant", "content": answer})
    save_history(chat_id, history)
    return answer


def prepare_image_prompt(text):
    if not has_persian(text):
        return text
    english = _chat([
        {"role": "system", "content": IMAGE_PROMPT_INSTRUCTION},
        {"role": "user", "content": text},
    ])
    return english or text


# ============================================================
# تولید محتوا: عکس، ویرایش عکس، صدا
# ============================================================
def _auth_headers():
    return {"Authorization": "Bearer " + POLLINATIONS_API_KEY}


def _get_bytes(url, params, timeout, expected_type):
    try:
        resp = requests.get(url, params=params, headers=_auth_headers(), timeout=timeout)
        resp.raise_for_status()
        ctype = resp.headers.get("Content-Type", "")
        if not ctype.startswith(expected_type):
            print("پاسخ نامعتبر (%s):" % expected_type, ctype, resp.text[:300])
            return None
        return resp.content
    except requests.RequestException as err:
        print("خطا در درخواست %s:" % expected_type, err)
        return None


def generate_image(prompt):
    if not POLLINATIONS_API_KEY:
        return None
    english = prepare_image_prompt(prompt)
    return _get_bytes(
        POLLINATIONS_IMAGE_URL + quote(english),
        {"model": IMAGE_MODEL, "width": IMAGE_WIDTH, "height": IMAGE_HEIGHT, "nologo": "true"},
        120, "image/",
    )


def edit_image(prompt, image_url):
    if not POLLINATIONS_API_KEY or not image_url:
        return None
    english = prepare_image_prompt(prompt)
    return _get_bytes(
        POLLINATIONS_IMAGE_URL + quote(english),
        {"model": EDIT_MODEL, "image": image_url, "width": IMAGE_WIDTH,
         "height": IMAGE_HEIGHT, "nologo": "true"},
        180, "image/",
    )


def _clean_for_speech(text):
    speech = text.replace("```", " ")
    for ch in ("/", "\\", "?", "#", "&", "%"):
        speech = speech.replace(ch, " ")
    return " ".join(speech.split())[:MAX_SPEECH_CHARS]


def generate_speech(text, voice):
    if not POLLINATIONS_API_KEY:
        print("ویس: کلید Pollinations تنظیم نشده است.")
        return None
    speech = _clean_for_speech(text)
    if not speech:
        return None
    try:
        resp = requests.get(
            POLLINATIONS_AUDIO_URL + quote(speech, safe=""),
            params={"voice": voice},
            headers=_auth_headers(),
            timeout=120,
        )
    except requests.RequestException as err:
        print("ویس: خطای شبکه:", err)
        return None

    ctype = resp.headers.get("Content-Type", "")
    if resp.status_code != 200 or not resp.content or ctype.startswith(("application/json", "text/")):
        print("ویس: پاسخ نامعتبر، کد:", resp.status_code, "نوع:", ctype, "متن:", resp.text[:300])
        return None
    print("ویس: صوت دریافت شد با صدای %s، حجم:" % voice, len(resp.content))
    return resp.content


def run_media_job(chat_id, label, done_text, error_text, build, caption=""):
    progress = Progress(chat_id, label).start()

    image_bytes = build()
    if not image_bytes:
        progress.finish(error_text)
        return

    try:
        bot.send_photo(chat_id, image_bytes, caption=caption)
    except TgAPIError as err:
        print("ارسال عکس ناموفق بود:", err)
        progress.finish(error_text)
        return

    progress.finish(done_text)


def get_incoming_image_url(message):
    photos = message.get("photo")
    file_id = None
    if isinstance(photos, list) and photos:
        file_id = photos[-1].get("file_id")
    else:
        doc = message.get("document") or {}
        if str(doc.get("mime_type", "")).startswith("image/"):
            file_id = doc.get("file_id")
    if not file_id:
        return None
    try:
        return bot.get_file_url(file_id)
    except TgAPIError as err:
        print("دریافت لینک عکس ناموفق بود:", err)
        return None


# ============================================================
# وضعیت گفتگو (در حافظه)
# ============================================================
MODE_IMAGE = "image"
MODE_EDIT_PHOTO = "edit_photo"
MODE_EDIT_PROMPT = "edit_prompt"

pending = {}


def set_mode(chat_id, mode, **extra):
    pending[chat_id] = dict(mode=mode, **extra)


def clear_mode(chat_id):
    pending.pop(chat_id, None)


# ============================================================
# منطق ربات
# ============================================================
def handle_message(chat_id, text, message):
    if text == "/start":
        clear_mode(chat_id)
        bot.send_message(chat_id, START_TEXT, keyboard=main_kb(chat_id))
        return

    if text == "/admin":
        if is_admin(chat_id):
            clear_mode(chat_id)
            show_admin_main(chat_id, None)
        else:
            bot.send_message(chat_id, NOT_ADMIN_TEXT, keyboard=main_kb(chat_id))
        return

    key = button_key(text)

    if key == BTN_HELP:
        bot.send_message(chat_id, HELP_TEXT, keyboard=main_kb(chat_id))
        return

    if key == BTN_CANCEL or key == BTN_BACK:
        clear_mode(chat_id)
        bot.send_message(chat_id, CANCEL_TEXT, keyboard=main_kb(chat_id))
        return

    if key == BTN_CLEAR:
        clear_mode(chat_id)
        clear_history(chat_id)
        progress = Progress(chat_id, LABEL_CLEAR).start()
        ids = pop_tracked_messages(chat_id)
        bot.delete_messages(chat_id, ids)
        progress.stop()
        bot.send_message(chat_id, HISTORY_CLEARED_TEXT, keyboard=main_kb(chat_id))
        return

    if key == BTN_VOICE_SETTINGS:
        clear_mode(chat_id)
        bot.send_message(chat_id, VOICE_SETTINGS_TEXT % get_voice(chat_id), keyboard=voice_keyboard())
        return

    if key in VOICE_OPTIONS:
        clear_mode(chat_id)
        save_voice(chat_id, key)
        bot.send_message(chat_id, VOICE_CHANGED_TEXT % key, keyboard=main_kb(chat_id))
        return

    if key == BTN_IMAGE:
        set_mode(chat_id, MODE_IMAGE)
        bot.send_message(chat_id, IMAGE_ASK_TEXT, keyboard=CANCEL_KEYBOARD)
        return

    if key == BTN_EDIT and EDIT_ENABLED:
        set_mode(chat_id, MODE_EDIT_PHOTO)
        bot.send_message(chat_id, EDIT_ASK_PHOTO, keyboard=CANCEL_KEYBOARD)
        return

    if key == BTN_VOICE:
        voice_set = get_voice_set()
        chat_key = str(chat_id)
        if chat_key in voice_set:
            voice_set.discard(chat_key)
            save_voice_set(voice_set)
            bot.send_message(chat_id, VOICE_OFF_TEXT, keyboard=main_kb(chat_id))
        else:
            voice_set.add(chat_key)
            save_voice_set(voice_set)
            bot.send_message(chat_id, VOICE_ON_TEXT, keyboard=main_kb(chat_id))
        return

    # ----- حالت‌های منتظر ورودی -----
    state = pending.get(chat_id)
    if state:
        mode = state["mode"]

        if mode == MODE_EDIT_PHOTO:
            url = get_incoming_image_url(message)
            if url:
                set_mode(chat_id, MODE_EDIT_PROMPT, image_url=url)
                bot.send_message(chat_id, EDIT_ASK_PROMPT, keyboard=CANCEL_KEYBOARD)
            else:
                bot.send_message(chat_id, "➜ عکس دریافت نشد. لطفاً یک عکس بفرست.", keyboard=CANCEL_KEYBOARD)
            return

        if not text:
            return

        if mode == MODE_EDIT_PROMPT:
            clear_mode(chat_id)
            image_url = state.get("image_url")
            run_media_job(
                chat_id, LABEL_EDIT, EDIT_DONE_TEXT, EDIT_ERROR,
                build=lambda: edit_image(text, image_url),
                caption=text[:200],
            )
            bot.send_message(chat_id, "➜ برای ادامه، سوال بپرس یا از دکمه‌ها استفاده کن.", keyboard=main_kb(chat_id))
            return

        clear_mode(chat_id)
        if mode == MODE_IMAGE:
            run_media_job(
                chat_id, LABEL_IMAGE, IMAGE_DONE_TEXT, IMAGE_ERROR,
                build=lambda: generate_image(text),
                caption=text[:200],
            )
        bot.send_message(chat_id, "➜ برای ادامه، سوال بپرس یا از دکمه‌ها استفاده کن.", keyboard=main_kb(chat_id))
        return

    if not text:
        return

    # ----- گفتگوی متنی (با نوار پیشرفت) -----
    progress = Progress(chat_id, LABEL_CHAT).start()
    started = time.time()

    answer = ask_ai(chat_id, text)

    elapsed = time.time() - started
    if elapsed < MIN_WAIT_SECONDS:
        time.sleep(MIN_WAIT_SECONDS - elapsed)

    if str(chat_id) in get_voice_set():
        progress.set_label(LABEL_VOICE)
        audio = generate_speech(answer, get_voice(chat_id))
        if audio:
            try:
                bot.send_voice(chat_id, audio)
                progress.finish(VOICE_DONE_TEXT)
                return
            except TgAPIError as err:
                print("ویس: ارسال به تلگرام ناموفق بود:", err)
        else:
            print("ویس: ساخت صدا ناموفق بود، پاسخ متنی فرستاده می‌شود.")

    deliver_answer(progress, answer)


def get_update(update):
    message = update.get("message")
    if not isinstance(message, dict):
        return None
    if (message.get("from") or {}).get("is_bot"):
        return None
    chat_id = (message.get("chat") or {}).get("id")
    if chat_id is None:
        return None
    text = (message.get("text") or message.get("caption") or "").strip()
    has_photo = bool(message.get("photo")) or str((message.get("document") or {}).get("mime_type", "")).startswith("image/")
    if not text and not has_photo:
        return None
    return chat_id, text, message


def handle_update(update):
    info = get_update(update)
    if not info:
        return
    chat_id, text, message = info

    track_message(chat_id, message.get("message_id"))
    update_user(chat_id, message.get("from") or {})

    admin = is_admin(chat_id)

    if not admin and is_banned(chat_id):
        bot.send_message(chat_id, BANNED_TEXT)
        return

    # پیام همگانی از پنل مدیریت
    if admin and chat_id in admin_pending and text and not is_button(text):
        admin_pending.pop(chat_id, None)
        do_broadcast(chat_id, text)
        return

    # سقف روزانه برای کاربران عادی (دکمه‌ها و دستورها شمرده نمی‌شوند)
    if not admin and not is_button(text):
        if not within_quota(chat_id):
            bot.send_message(chat_id, LIMIT_REACHED_TEXT, keyboard=main_kb(chat_id))
            return

    handle_message(chat_id, text, message)


def drain_old_updates():
    offset = kv_get("offset_id")
    if offset is not None:
        return offset
    print("اولین اجرا: پیام‌های قدیمی نادیده گرفته می‌شوند.")
    updates = bot.get_updates(offset=-1)
    offset = updates[-1]["update_id"] + 1 if updates else 0
    kv_set("offset_id", offset)
    return offset


def main():
    if not BOT_TOKEN:
        print("توکن ربات تنظیم نشده است. متغیر BOT_TOKEN را تنظیم کنید.")
        sys.exit(1)
    if not POLLINATIONS_API_KEY:
        print("هشدار: POLLINATIONS_API_KEY تنظیم نشده است.")
    if not ADMIN_IDS:
        print("هشدار: ADMIN_ID تنظیم نشده است؛ پنل مدیریت فعال نیست.")

    # سرور keep-alive برای اینکه Render سرویس را خاموش نکند
    start_keepalive_server()

    print("ربات تلگرام در حال اجراست. برای توقف Ctrl+C را بزنید.")

    try:
        offset = drain_old_updates()
    except TgAPIError as err:
        print("خطا در خواندن پیام‌های قدیمی:", err)
        offset = kv_get("offset_id", 0)

    delay = 2
    while True:
        try:
            updates = bot.get_updates(offset=offset)
            delay = 2

            for update in updates:
                offset = update["update_id"] + 1
                kv_set("offset_id", offset)

                try:
                    if "callback_query" in update:
                        handle_callback(update["callback_query"])
                    else:
                        handle_update(update)
                except TgAPIError as err:
                    print("خطای تلگرام:", err)
                except Exception as err:
                    print("خطای داخلی در پردازش:", err)

        except KeyboardInterrupt:
            print("ربات متوقف شد.")
            sys.exit(0)
        except TgAuthError as err:
            print("خطا:", err)
            sys.exit(1)
        except TgAPIError as err:
            print("خطا:", err)
            time.sleep(delay)
            delay = min(delay * 2, 60)
        except requests.RequestException as err:
            print("خطای شبکه:", err)
            time.sleep(delay)
            delay = min(delay * 2, 60)


if __name__ == "__main__":
    main()
