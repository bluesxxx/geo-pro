<?php

/**
 * Zero-dependency test runner for the audit-engine package.
 * Run: php tests/run.php   (inside packages/audit-engine)
 */

require __DIR__.'/../src/autoload.php';

use GeoPro\AuditEngine\AuditEngine;
use GeoPro\AuditEngine\AuditException;
use GeoPro\AuditEngine\AuditResult;
use GeoPro\AuditEngine\CurlWebPageFetcher;
use GeoPro\AuditEngine\FeatureExtractor;
use GeoPro\AuditEngine\GeoAuditScorer;
use GeoPro\AuditEngine\HeuristicScorer;
use GeoPro\AuditEngine\IssuePresenter;
use GeoPro\AuditEngine\PageSnapshot;
use GeoPro\AuditEngine\WebPageFetcherInterface;

$tests = 0;
$failures = 0;

function check(string $name, bool $ok, string $detail = ''): void
{
    global $tests, $failures;
    $tests++;
    if (! $ok) {
        $failures++;
        echo "FAIL  $name".($detail !== '' ? "  --  $detail" : '')."\n";
    }
}

function same(mixed $expected, mixed $actual): bool
{
    return $expected === $actual;
}

/* ============ FeatureExtractor ============ */

$htmlFull = file_get_contents(__DIR__.'/fixture_full.html');

$htmlBare = '<html><head><title>标题</title></head><body><p>你好</p></body></html>';

$ex = new FeatureExtractor();

$f = $ex->extract($htmlFull);
check('extract: has_h1', same(true, $f['has_h1']));
check('extract: has_schema', same(true, $f['has_schema']));
check('extract: has_faq_schema', same(true, $f['has_faq_schema']));
check('extract: title', same('GEO 优化指南', $f['title']));
check('extract: meta_description', same('网站 GEO 优化完整指南。', $f['meta_description']));
check('extract: text_length > 0', $f['text_length'] > 0);
check('extract: buzzword_count 0', same(0, $f['buzzword_count']));

$b = $ex->extract($htmlBare);
check('extract(bare): has_h1 false', same(false, $b['has_h1']));
check('extract(bare): has_schema false', same(false, $b['has_schema']));
check('extract(bare): has_faq_schema false', same(false, $b['has_faq_schema']));
check('extract(bare): text_length >= 2', $b['text_length'] >= 2);

$htmlBuzz = '<html><body><h1>极致领先</h1><p>最好、最牛、无敌、革命性、史上最强、颠覆。</p></body></html>';
$z = $ex->extract($htmlBuzz);
check('extract: buzzword_count', same(8, $z['buzzword_count']), 'got '.$z['buzzword_count']);

$e = $ex->extract('');
check('extract: empty html safe', same(false, $e['has_h1']) && $e['text_length'] === 0);

/* ============ HeuristicScorer ============ */

$scorer = new HeuristicScorer();

$fullScore = $scorer->score($f, $f['text']);
check('score: full page', same(95, $fullScore['score']), 'got '.$fullScore['score']);
check('score: full page missing_faq false', same(false, $fullScore['missing_faq']));
check('score: full page missing_schema false', same(false, $fullScore['missing_schema']));
check('score: full page suggestions empty', same(true, $fullScore['suggestions'] === [] || $fullScore['suggestions'][0] !== ''));

$bareScore = $scorer->score($b, $b['text']);
check('score: bare page 30', same(30, $bareScore['score']), 'got '.$bareScore['score']);
check('score: bare page missing_faq true', same(true, $bareScore['missing_faq']));
check('score: bare page suggestions has h1', in_array('补充一个清晰的 <h1> 主标题，帮助 AI 理解页面主题。', $bareScore['suggestions'], true));

$buzzScore = $scorer->score($z, $z['text']);
check('score: buzz penalty', same(35, $buzzScore['score']), 'got '.$buzzScore['score']); // 40+20 - min(8*3,15)=15 => 45? recompute below

/* buzz page: has_h1 true => 40+20=60; len small (<200) => -10 => 50; buzz 8 => -15 => 35 */
check('score: buzz penalty recompute', same(35, $buzzScore['score']), 'got '.$buzzScore['score']);

$midText = str_repeat('普通内容', 60); // 240 bytes, 200..800
$mid = ['has_h1' => true, 'has_schema' => true, 'has_faq_schema' => true, 'buzzword_count' => 0];
$midScore = $scorer->score($mid, $midText);
check('score: mid length no bonus', same(85, $midScore['score']), 'got '.$midScore['score']);

$longText = str_repeat('a', 900);
$longScore = $scorer->score(['has_h1' => true, 'has_schema' => true, 'has_faq_schema' => true, 'buzzword_count' => 0], $longText);
check('score: long length bonus', same(95, $longScore['score']), 'got '.$longScore['score']);

$suggestionsCapped = $scorer->score(['has_h1' => false, 'has_schema' => false, 'has_faq_schema' => false, 'buzzword_count' => 1], str_repeat('x', 500));
check('score: suggestions capped at 5', count($suggestionsCapped['suggestions']) <= 5, 'got '.count($suggestionsCapped['suggestions']));

$perfectSuggestions = $scorer->score(['has_h1' => true, 'has_schema' => true, 'has_faq_schema' => true, 'buzzword_count' => 0], str_repeat('x', 900));
check('score: perfect page has positive suggestion', in_array('整体结构良好，可进一步补充原创数据与权威引用以拉开差距。', $perfectSuggestions['suggestions'], true));

/* ============ AuditEngine ============ */

$fakeFetcher = new class($htmlFull) implements WebPageFetcherInterface {
    public function __construct(private string $html) {}

    public function fetch(string $url): string
    {
        return $this->html;
    }
};

// Deep engine (GeoAudit rules): llms.txt probes return 404 deterministically.
$probe404 = static fn (string $url): array => ['status' => 404, 'body' => ''];

$engine = new AuditEngine($fakeFetcher, new HeuristicScorer(), $probe404);
$result = $engine->run('https://example.com/guide');
check('engine: instance', $result instanceof AuditResult);
check('engine: url', same('https://example.com/guide', $result->url));
check('engine: raw_features has title', same('GEO 优化指南', $result->rawFeatures['title'] ?? null));
check('engine: no error', same(null, $result->error));

/* fixture_full.html readiness math:
 * meta:      title/desc/h1 pass; canonical/robots/og_title/og_image/twitter fail => 3/8
 * jsonld:    present pass; org/website/breadcrumb fail (block_count numeric + schema_types
 *            listing skipped)                                              => 1/4
 * structured: faq+article pass, howto/product fail                            => 2/4
 * llms_txt:  both files 404                                                   => 0/2
 * content:   only "faq" matched                                               => 1/9
 * total scoreable = 27, passed = 7 -> readiness = round(7/27*100) = 26
 */
check('engine: readiness score 26', same(26, $result->score), 'got '.$result->score);
check('engine: passed_checks 7', same(7, $result->passedChecks), 'got '.$result->passedChecks);
check('engine: total_checks 27', same(27, $result->totalChecks), 'got '.$result->totalChecks);

$categoryIds = array_map(fn ($c) => $c['id'], $result->categories);
check('engine: category ids', same(['meta', 'structured', 'ai_ready', 'content'], $categoryIds), json_encode($categoryIds));

$metaCat = null;
foreach ($result->categories as $c) {
    if ($c['id'] === 'meta') {
        $metaCat = $c;
    }
}
check('engine: meta category tally', $metaCat !== null && $metaCat['passed'] === 3 && $metaCat['total'] === 8,
    json_encode($metaCat ?? []));

check('engine: first issue is critical', ($result->issues[0]['severity'] ?? '') === 'critical',
    json_encode($result->issues[0] ?? []));
check('engine: issues contain llms_txt_present', in_array('llms_txt_present', array_column($result->issues, 'code'), true));
check('engine: informational schema listing not an issue', ! in_array('jsonld_schema_types', array_column($result->issues, 'code'), true));

$arr = $result->toArray();
check('engine: toArray legacy keys', isset($arr['missing_faq'], $arr['missing_schema'], $arr['suggestions'], $arr['raw_features']));
check('engine: toArray new keys', isset($arr['categories'], $arr['issues'], $arr['passed'], $arr['facts'], $arr['total_checks'], $arr['passed_checks']));
check('engine: legacy missing_faq false', same(false, $arr['missing_faq']));

// Transport failure on the llms probe maps to status=error facts, still scored as failures.
$probeThrows = static function (string $url): array {
    throw new \RuntimeException('dns timeout');
};
$engineErr = new AuditEngine($fakeFetcher, new HeuristicScorer(), $probeThrows);
$resultErr = $engineErr->run('https://example.com/guide');
check('engine: llms transport error scored', same(26, $resultErr->score), 'got '.$resultErr->score);
$errFacts = array_values(array_filter($resultErr->facts, fn ($f) => $f['key'] === 'llms_txt.present'));
check('engine: llms error status', (($errFacts[0]['status'] ?? '') === 'error'), json_encode($errFacts[0] ?? []));

$failingFetcher = new class implements WebPageFetcherInterface {
    public function fetch(string $url): string
    {
        throw new AuditException('目标地址不允许访问');
    }
};
$failed = (new AuditEngine($failingFetcher))->run('https://127.0.0.1/');
check('engine: failure result', $failed instanceof AuditResult && $failed->error === '目标地址不允许访问');
check('engine: failure score 0', same(0, $failed->score));

/* ============ PageSnapshot ============ */

$snap = PageSnapshot::fromHtml('https://example.com/guide', $htmlFull);
check('snapshot: metadata title', same('GEO 优化指南', $snap->metadata['title']));
check('snapshot: metadata description', same('网站 GEO 优化完整指南。', $snap->metadata['description']));
check('snapshot: jsonld blocks count', same(2, count($snap->jsonLdBlocks)));
check('snapshot: headings include h1 text', str_contains($snap->headings, '生成式引擎优化完全指南'));
check('snapshot: empty html safe', PageSnapshot::fromHtml('https://x.test/', '')->text === '');

/* ============ GeoAuditScorer ============ */

check('scorer: null when nothing scoreable', same(null, GeoAuditScorer::score([
    ['key' => 'jsonld.block_count', 'status' => '3'],
    ['key' => 'jsonld.schema_types', 'status' => 'Organization'],
])));
check('scorer: half passed rounds to 50', same(50, GeoAuditScorer::score([
    ['key' => 'a', 'status' => 'present'],
    ['key' => 'b', 'status' => 'absent'],
])));

/* ============ IssuePresenter ============ */

$pres = IssuePresenter::build([
    ['check' => 'meta', 'key' => 'meta.title', 'status' => 'absent', 'evidence' => null],
    ['check' => 'meta', 'key' => 'meta.description', 'status' => 'present', 'evidence' => 'desc'],
    ['check' => 'llms_txt', 'key' => 'llms_txt.parse_error', 'status' => 'parse_error', 'evidence' => 'empty body'],
    ['check' => 'jsonld', 'key' => 'jsonld.schema_types', 'status' => 'Article, FAQPage', 'evidence' => null],
    ['check' => 'jsonld', 'key' => 'jsonld.block_count', 'status' => '2', 'evidence' => null],
]);

check('presenter: one issue for absent title', count(array_filter($pres['issues'], fn ($i) => $i['code'] === 'meta_title')) === 1);
$titleIssue = array_values(array_filter($pres['issues'], fn ($i) => $i['code'] === 'meta_title'))[0] ?? null;
check('presenter: severity critical', $titleIssue !== null && $titleIssue['severity'] === 'critical');
check('presenter: parse_error becomes issue', count(array_filter($pres['issues'], fn ($i) => $i['code'] === 'llms_txt_parse_error')) === 1);
check('presenter: informational statuses skipped', count($pres['issues']) === 2, 'got '.count($pres['issues']));
check('presenter: passed collected', count($pres['passed']) === 1 && $pres['passed'][0]['code'] === 'meta_description');

$order = IssuePresenter::build([
    ['key' => 'meta.og_image', 'status' => 'absent'],
    ['key' => 'meta.title', 'status' => 'absent'],
    ['key' => 'meta.description', 'status' => 'absent'],
]);
check('presenter: critical before warning before info',
    same(['critical', 'warning', 'info'], array_column($order['issues'], 'severity')));

/* ============ CurlWebPageFetcher SSRF guard (no network) ============ */

$fetcher = new CurlWebPageFetcher();
$caught = null;
try {
    $fetcher->fetch('http://127.0.0.1/');
} catch (AuditException $e) {
    $caught = $e->getMessage();
}
check('fetcher: blocks localhost', $caught !== null, 'expected AuditException');

$caught2 = null;
try {
    $fetcher->fetch('ftp://example.com/file');
} catch (AuditException $e) {
    $caught2 = $e->getMessage();
}
check('fetcher: blocks non-http scheme', $caught2 !== null);

$caught3 = null;
try {
    $fetcher->fetch('http://192.168.1.1/');
} catch (AuditException $e) {
    $caught3 = $e->getMessage();
}
check('fetcher: blocks private ip', $caught3 !== null);

/* ============ CurlWebPageFetcher: must return the real body (regression) ============ */
// Regression for the bug where, with CURLOPT_WRITEFUNCTION set, curl_exec()
// returns a boolean instead of the body, so fetch() returned "1". We spin up a
// tiny local HTTP server (no external network) and assert the real HTML comes back.

$sampleHtml = '<!doctype html><html><head><title>测试页</title>'
    .'<meta name="description" content="测试页描述">'
    .'<script type="application/ld+json">{"@type":"FAQPage","mainEntity":[]}</script>'
    .'</head><body><h1>主标题</h1><p>'.str_repeat('正文内容 ', 80).'</p></body></html>';

$serverScript = sys_get_temp_dir().'/jetsocio_audit_test_server.php';
file_put_contents($serverScript, "<?php\n"
    ."\$sock = @stream_socket_server('tcp://127.0.0.1:0', \$e, \$m);\n"
    ."if (!\$sock) { fwrite(STDERR, \"FAIL \$e \$m\\n\"); exit(1); }\n"
    ."\$port = (int) parse_url((string) stream_socket_get_name(\$sock, false), PHP_URL_PORT);\n"
    ."fwrite(STDOUT, \$port.\"\\n\");\n"
    ."if ((\$conn = @stream_socket_accept(\$sock, 10)) !== false) {\n"
    ."  fgets(\$conn);\n"
    ."  \$body = ".var_export($sampleHtml, true).";\n"
    ."  \$len = strlen(\$body);\n"
    ."  fwrite(\$conn, \"HTTP/1.1 200 OK\\r\\nContent-Type: text/html; charset=utf-8\\r\\nContent-Length: \$len\\r\\nConnection: close\\r\\n\\r\\n\$body\");\n"
    ."  fclose(\$conn);\n"
    ."}\n"
    ."fclose(\$sock);\n");

$proc = @proc_open('php '.escapeshellarg($serverScript), [
    ['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w'],
], $pipes);

if ($proc === false) {
    check('fetcher: local server start', false, 'proc_open failed');
} else {
    $portLine = fgets($pipes[1]);
    $port = (int) trim((string) $portLine);
    fclose($pipes[0]);
    if ($port > 0) {
        // Relax the SSRF guard so the local server is reachable in the test only.
        $testFetcher = new class(10, 2 * 1024 * 1024, 3) extends CurlWebPageFetcher {
            protected function assertSafeUrl(string $url): void
            {
                $parts = parse_url($url);
                if (! in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)) {
                    throw new AuditException('仅支持 http/https 链接');
                }
            }
        };

        $fetched = $testFetcher->fetch('http://127.0.0.1:'.$port.'/');
        check('fetcher: returns real body (not "1")', $fetched !== '1' && str_contains($fetched, '<h1>主标题</h1>'), 'got '.substr($fetched, 0, 40));
        $feat = (new FeatureExtractor())->extract($fetched);
        check('fetcher+extract: has_h1 true', same(true, $feat['has_h1']));
        check('fetcher+extract: has_schema true', same(true, $feat['has_schema']));
        check('fetcher+extract: has_faq_schema true', same(true, $feat['has_faq_schema']));
        check('fetcher+extract: text_length>100', $feat['text_length'] > 100, 'got '.$feat['text_length']);
    } else {
        check('fetcher: read server port', false, 'no port from server');
    }
    fclose($pipes[1]);
    fclose($pipes[2]);
    @proc_terminate($proc);
    @proc_close($proc);
}

/* ============ summary ============ */

echo "\n{$tests} tests, {$failures} failures\n";

exit($failures === 0 ? 0 : 1);
