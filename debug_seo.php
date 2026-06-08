<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\SeoLog;
use App\Models\Site;

function dumpSite($id){
    $site = Site::find($id);
    echo "--- Site {$id} ---\n";
    echo "performance_score: " . ($site->performance_score ?? 'NULL') . "\n";
    echo "lcp_ms: " . ($site->lcp_ms ?? 'NULL') . "\n";
    echo "fcp_ms: " . ($site->fcp_ms ?? 'NULL') . "\n";
    echo "checked_at (latest seo_log): \n";
    $logs = SeoLog::where('site_id',$id)->orderBy('checked_at','desc')->take(5)->get();
    foreach($logs as $l){
        $perf = data_get($l->lighthouse,'categories.performance.score');
        echo $l->id . ' | ' . $l->checked_at . ' | perf: ' . ($perf !== null ? $perf : 'NULL') . "\n";
    }
}

dumpSite(1);

dumpSite(2);

echo "Done.\n";
