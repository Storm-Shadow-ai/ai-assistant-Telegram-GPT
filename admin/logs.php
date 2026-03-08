<?php
require_once __DIR__ . '/../app/util.php';
require_admin();
$pdo = db();
try { if (function_exists('ensure_schema_base')) ensure_schema_base($pdo); if (function_exists('ensure_schema_v3')) ensure_schema_v3($pdo); } catch (Throwable $e) {}


$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$from = isset($_GET['from']) ? trim((string)$_GET['from']) : '';
$to = isset($_GET['to']) ? trim((string)$_GET['to']) : '';
$status = isset($_GET['status']) ? trim((string)$_GET['status']) : '';
$export = isset($_GET['export']) ? (int)$_GET['export'] : 0;

$where = [];
$params = [];
if ($userId>0) { $where[] = "l.tg_user_id=?"; $params[] = $userId; }
if ($from!=='') { $where[] = "l.created_at >= ?"; $params[] = $from . " 00:00:00"; }
if ($to!=='') { $where[] = "l.created_at <= ?"; $params[] = $to . " 23:59:59"; }
if ($status==='ok' || $status==='error' || $status==='started') { $where[] = "l.status = ?"; $params[] = $status; }

$sql = "SELECT l.*, u.username FROM ai_logs l LEFT JOIN tg_users u ON u.id=l.tg_user_id";
if ($where) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " ORDER BY l.id DESC LIMIT 500";

$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

if ($export === 1) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="ai_logs.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['id','created_at','tg_user_id','username','model','status','latency_ms','input_tokens','output_tokens','total_tokens','prompt','response','error']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id'], $r['created_at'], $r['tg_user_id'], $r['username'],
            $r['model'], $r['status'], $r['latency_ms'],
            $r['input_tokens'] ?? 0,
            $r['output_tokens'] ?? 0,
            $r['total_tokens'] ?? (($r['input_tokens'] ?? 0) + ($r['output_tokens'] ?? 0)),
            mb_substr((string)$r['prompt_text'], 0, 2000, 'UTF-8'),
            mb_substr((string)$r['response_text'], 0, 2000, 'UTF-8'),
            mb_substr((string)$r['error_text'], 0, 500, 'UTF-8'),
        ]);
    }
    fclose($out);
    exit;
}

include __DIR__.'/_layout_top.php';
?>
<h2>Логи</h2>

<form method="get" style="display:grid;grid-template-columns: 1fr 1fr 1fr 1fr auto auto; gap:10px; align-items:end;">
  <div>
    <div class="small">User ID</div>
    <input name="user_id" value="<?=h($userId?:'')?>">
  </div>
  <div>
    <div class="small">From (YYYY-MM-DD)</div>
    <input name="from" value="<?=h($from)?>" placeholder="2026-03-01">
  </div>
  <div>
    <div class="small">To (YYYY-MM-DD)</div>
    <input name="to" value="<?=h($to)?>" placeholder="2026-03-31">
  </div>
  <div>
    <div class="small">Status</div>
    <select name="status">
      <option value="" <?=($status===''?'selected':'')?>>all</option>
      <option value="ok" <?=($status==='ok'?'selected':'')?>>ok</option>
      <option value="error" <?=($status==='error'?'selected':'')?>>error</option>
      <option value="started" <?=($status==='started'?'selected':'')?>>started</option>
    </select>
  </div>
  <div><button class="btn2" type="submit">Фильтр</button></div>
  <div><a class="btn" style="text-decoration:none;display:inline-block;" href="/admin/logs.php?<?=h(http_build_query(array_merge($_GET, ['export'=>1])))?>">Экспорт CSV</a></div>
</form>

<div class="small" style="margin:10px 0;">Показано до 500 записей.</div>

<table>
<tr>
  <th>ID</th><th>Time</th><th>User</th><th>Model</th><th>Status</th><th>ms</th><th>Tok(in)</th><th>Tok(out)</th><th>Tok(total)</th><th>Prompt</th><th>Response</th><th>Error</th>
</tr>
<?php foreach($rows as $r): ?>
<tr>
  <td><?=h($r['id'])?></td>
  <td><?=h($r['created_at'])?></td>
  <td><?=h(($r['username']?('@'.$r['username'].' '):'').$r['tg_user_id'])?></td>
  <td><?=h($r['model'])?></td>
  <td><?=h($r['status'])?></td>
  <td><?=h($r['latency_ms'])?></td>
  <td><?=h($r['input_tokens'] ?? 0)?></td>
  <td><?=h($r['output_tokens'] ?? 0)?></td>
  <td><?=h($r['total_tokens'] ?? (($r['input_tokens'] ?? 0) + ($r['output_tokens'] ?? 0)))?></td>
  <td style="max-width:300px;white-space:pre-wrap;"><?=h($r['prompt_text'])?></td>
  <td style="max-width:360px;white-space:pre-wrap;"><?=h($r['response_text'])?></td>
  <td style="max-width:220px;white-space:pre-wrap;"><?=h($r['error_text'])?></td>
</tr>
<?php endforeach; ?>
</table>

<?php include __DIR__.'/_layout_bottom.php'; ?>
