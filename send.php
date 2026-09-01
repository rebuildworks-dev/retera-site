<?php
/**
 * Retera 製品サイト — 問い合わせフォームの送信（2026-08-17）
 *
 * contact.html の form から POST を受け、office@rebuildworks.co.jp へメールを送る。
 * 送信後は contact.html?sent=1 へ戻し、結果の表示はそちらの JS が行う。
 *
 * 置き場所: 製品サイトと同じディレクトリ（/public_html/retera/）
 * 動作環境: エックスサーバー PHP 7.4 / sendmail
 *
 * 宛先を変えるときは下の $TO だけを直す。
 */

mb_language('Japanese');
mb_internal_encoding('UTF-8');

$TO      = 'office@rebuildworks.co.jp';
// 送信元は必ず同じドメインのアドレスにする。
// 差出人を問い合わせ者のアドレスにすると SPF に引っかかって迷惑メール扱いされる。
$FROM    = 'office@rebuildworks.co.jp';
$SUBJECT = '【Retera】サイトからのお問い合わせ';
$BACK    = 'contact.html';

/** 入力画面へ戻す（PRG。再読み込みで二重送信されないよう 303） */
function back_to(string $query): void
{
    global $BACK;
    header('Location: ' . $BACK . $query, true, 303);
    exit;
}

/** POST 値を 1 つ取り出す */
function post_value(string $key): string
{
    return isset($_POST[$key]) ? trim((string) $_POST[$key]) : '';
}

/** メールヘッダーに入れる値から改行を落とす（ヘッダインジェクション対策） */
function header_safe(string $value): string
{
    return str_replace(["\r", "\n", "\0"], '', $value);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    back_to('?error=method');
}

// 迷惑メール対策のおとり。人には見えない項目なので、埋まっていたら黙って捨てる
// （送信元には成功したように見せる。ボットに失敗を教えない）
if (post_value('website') !== '') {
    back_to('?sent=1');
}

$company = post_value('company');
$name    = post_value('name');
$email   = post_value('email');
$consent = post_value('consent');

if ($company === '' || $name === '' || $email === '' || $consent === '') {
    back_to('?error=required');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    back_to('?error=email');
}

$business = '';
if (isset($_POST['business']) && is_array($_POST['business'])) {
    $business = implode('、', array_map('strval', $_POST['business']));
}

$lines = [
    '会社名　　　　: ' . $company,
    'お名前　　　　: ' . $name,
    '役職　　　　　: ' . post_value('title'),
    'メールアドレス: ' . $email,
    '電話番号　　　: ' . post_value('tel'),
    '社員数　　　　: ' . post_value('size'),
    '主な事業　　　: ' . $business,
    'ご検討の時期　: ' . post_value('timing'),
    '',
    '─── いま使っているシステム ───',
    post_value('systems') !== '' ? post_value('systems') : '（記入なし）',
    '',
    '─── 困っていること ───',
    post_value('issue') !== '' ? post_value('issue') : '（記入なし）',
    '',
    '──────────────────',
    '送信日時: ' . date('Y-m-d H:i:s'),
    '送信元IP: ' . ($_SERVER['REMOTE_ADDR'] ?? '不明'),
    'ページ　: https://rebuildworks.co.jp/retera/contact.html',
];
$body = implode("\n", $lines);

$headers = implode("\r\n", [
    'From: ' . mb_encode_mimeheader('Retera 製品サイト', 'UTF-8') . ' <' . $FROM . '>',
    'Reply-To: ' . header_safe($email),
    'X-Mailer: PHP/' . phpversion(),
]);

// 第5引数の -f で envelope-from を明示する。これが無いとサーバー既定の
// 差出人になり、受信側で迷惑メールに振り分けられやすい。
$sent = mb_send_mail($TO, $SUBJECT, $body, $headers, '-f' . $FROM);

back_to($sent ? '?sent=1' : '?error=send');
