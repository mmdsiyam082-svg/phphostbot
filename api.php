<?php
declare(strict_types=1);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
header('Content-Type: application/json; charset=utf-8');

const PROJECT_ROOT = '/var/www/html/sites';

function out(array $data, int $status=200): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    exit;
}
function apiKey(): string { return trim((string)getenv('HOSTING_API_KEY')); }
function auth(): void {
    $expected=apiKey();
    $provided=trim((string)($_SERVER['HTTP_X_API_KEY'] ?? ''));
    if ($expected==='' || $provided==='' || !hash_equals($expected,$provided)) {
        out(['ok'=>false,'error'=>'Unauthorized'],401);
    }
}
function validName(string $n): bool { return (bool)preg_match('/^[A-Za-z0-9_-]{1,40}$/',$n); }
function pathFor(string $n): string { return PROJECT_ROOT.'/'.$n; }
function countProjects(): int {
    if (!is_dir(PROJECT_ROOT)) return 0;
    $n=0;
    foreach (scandir(PROJECT_ROOT) ?: [] as $x) {
        if ($x!=='.' && $x!=='..' && is_dir(pathFor($x))) $n++;
    }
    return $n;
}
function dirSize(string $dir): int {
    if (!is_dir($dir)) return 0;
    $size=0;
    try {
        $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS));
        foreach($it as $f) if($f->isFile()) $size+=(int)$f->getSize();
    } catch(Throwable $e) {}
    return $size;
}
function deleteDir(string $dir): bool {
    if (!is_dir($dir)) return true;
    try {
        $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
        foreach($it as $f) {
            if ($f->isDir() && !$f->isLink()) @rmdir($f->getPathname());
            else @unlink($f->getPathname());
        }
        return @rmdir($dir);
    } catch(Throwable $e) { return false; }
}
function safeZipPath(string $name): bool {
    $name=str_replace('\\','/',$name);
    if ($name==='' || str_starts_with($name,'/') || preg_match('/^[A-Za-z]:\//',$name)) return false;
    foreach(explode('/',$name) as $p) if ($p==='..') return false;
    return true;
}
function removeDangerous(string $dir): void {
    if (!is_dir($dir)) return;
    try {
        $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS));
        foreach($it as $f) if ($f->isFile() && in_array($f->getFilename(),['.env','.user.ini','.htaccess'],true)) @unlink($f->getPathname());
    } catch(Throwable $e) {}
}
function normalizeRoot(string $dir): void {
    $items=array_values(array_filter(scandir($dir) ?: [],fn($x)=>$x!=='.'&&$x!=='..'));
    if(count($items)!==1 || !is_dir($dir.'/'.$items[0])) return;
    $nested=$dir.'/'.$items[0];
    foreach(array_values(array_filter(scandir($nested) ?: [],fn($x)=>$x!=='.'&&$x!=='..')) as $x) @rename($nested.'/'.$x,$dir.'/'.$x);
    @rmdir($nested);
}
function upload(): void {
    auth();
    if (!isset($_FILES['project_file'])) out(['ok'=>false,'error'=>'project_file ZIP is required'],400);
    $f=$_FILES['project_file'];
    if (($f['error']??1)!==UPLOAD_ERR_OK) out(['ok'=>false,'error'=>'Upload failed','code'=>$f['error']??null],400);
    $max=((int)(getenv('MAX_UPLOAD_MB')?:50))*1024*1024;
    if ((int)$f['size']>$max) out(['ok'=>false,'error'=>'File exceeds upload limit'],413);
    $name=trim((string)($_POST['project']??''));
    if (!validName($name)) out(['ok'=>false,'error'=>'Invalid project name'],400);
    $maxProjects=(int)(getenv('MAX_PROJECTS')?:100);
    if(countProjects()>=$maxProjects && !is_dir(pathFor($name))) out(['ok'=>false,'error'=>'Maximum project limit reached'],429);
    if(strtolower(pathinfo((string)$f['name'],PATHINFO_EXTENSION))!=='zip') out(['ok'=>false,'error'=>'Only ZIP files are supported'],400);
    $dest=pathFor($name);
    if(is_dir($dest)) out(['ok'=>false,'error'=>'Project already exists'],409);
    if(!mkdir($dest,0755,true)) out(['ok'=>false,'error'=>'Cannot create project'],500);
    $zip=new ZipArchive();
    if($zip->open((string)$f['tmp_name'])!==true){ deleteDir($dest); out(['ok'=>false,'error'=>'Invalid ZIP'],400); }
    for($i=0;$i<$zip->numFiles;$i++){
        $s=$zip->statIndex($i);
        if(!$s || !safeZipPath((string)$s['name'])){
            $zip->close(); deleteDir($dest); out(['ok'=>false,'error'=>'Unsafe ZIP path'],400);
        }
    }
    if(!$zip->extractTo($dest)){ $zip->close(); deleteDir($dest); out(['ok'=>false,'error'=>'Extraction failed'],500); }
    $zip->close();
    normalizeRoot($dest);
    removeDangerous($dest);
    @chown($dest,'www-data'); @chmod($dest,0755);
    out([
        'ok'=>true,
        'message'=>'Project hosted successfully',
        'project'=>$name,
        'url'=>'/sites/'.rawurlencode($name).'/',
        'full_url'=>'/sites/'.rawurlencode($name).'/',
        'size'=>dirSize($dest),
        'php'=>PHP_VERSION
    ],201);
}
function listProjects(): void {
    auth(); $result=[];
    if(is_dir(PROJECT_ROOT)) foreach(scandir(PROJECT_ROOT) ?: [] as $n){
        if($n==='.'||$n==='..'||!is_dir(pathFor($n))) continue;
        $result[]=['name'=>$n,'url'=>'/sites/'.rawurlencode($n).'/','size'=>dirSize(pathFor($n)),'created_at'=>date(DATE_ATOM,(int)filemtime(pathFor($n)))];
    }
    out(['ok'=>true,'count'=>count($result),'projects'=>$result]);
}
function removeProject(): void {
    auth();
    $name=trim((string)($_GET['project']??''));
    if(!validName($name)) out(['ok'=>false,'error'=>'Invalid project name'],400);
    if(!is_dir(pathFor($name))) out(['ok'=>false,'error'=>'Project not found'],404);
    if(!deleteDir(pathFor($name))) out(['ok'=>false,'error'=>'Delete failed'],500);
    out(['ok'=>true,'message'=>'Project deleted','project'=>$name]);
}
$action=(string)($_GET['action']??'health');
if($action==='health') out([
    'ok'=>true,'service'=>'Real PHP Hosting','php'=>PHP_VERSION,
    'zip_extension'=>class_exists('ZipArchive'),
    'storage_path'=>PROJECT_ROOT,
    'storage_exists'=>is_dir(PROJECT_ROOT),
    'storage_writable'=>is_writable(PROJECT_ROOT),
    'projects'=>countProjects(),
    'time'=>date(DATE_ATOM)
]);
if($action==='upload' && $_SERVER['REQUEST_METHOD']==='POST') upload();
if($action==='projects' && $_SERVER['REQUEST_METHOD']==='GET') listProjects();
if($action==='delete' && $_SERVER['REQUEST_METHOD']==='DELETE') removeProject();
out(['ok'=>false,'error'=>'Unknown action or method'],404);
