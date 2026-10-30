<?php
declare(strict_types=1);

final class TrackingConflict extends RuntimeException {}

function tracking_statuses(): array
{
    return ['analyze'=>'À analyser', 'affected'=>'Concerné', 'progress'=>'En cours', 'fixed'=>'Corrigé', 'unaffected'=>'Non concerné', 'accepted'=>'Risque accepté'];
}

function tracking_priorities(): array
{
    return ['urgent'=>'Urgente', 'high'=>'Haute', 'normal'=>'Normale', 'low'=>'Basse'];
}

function tracking_fields(): array
{
    return ['status'=>'Statut', 'priority'=>'Priorité interne', 'owner'=>'Responsable', 'due_date'=>'Échéance interne',
        'review_date'=>'Date de réexamen', 'scope'=>'Systèmes et versions concernés', 'mitigation'=>'Mesures et plan de remédiation',
        'decision'=>'Justification de la décision', 'evidence_url'=>'Lien justificatif', 'ticket_url'=>'Lien du ticket'];
}

function tracking_id(string $id): string
{
    $id = strtoupper(trim($id));
    if (strlen($id)>64 || !preg_match('/^CVE-\d{4}-\d{4,}$/D', $id)) throw new InvalidArgumentException('Identifiant CVE invalide (ex. CVE-2026-12345).');
    return $id;
}

function tracking_text(array $data, string $key, int $limit, bool $required = false): string
{
    $value = input($key, '', $data);
    if (($required && $value==='') || mb_strlen($value)>$limit || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $value)) {
        throw new InvalidArgumentException('Champ invalide : '.(tracking_fields()[$key] ?? $key).' (maximum '.$limit.' caractères).');
    }
    return $value;
}

function tracking_date(string $date): string
{
    if ($date==='') return '';
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$parsed || $parsed->format('Y-m-d')!==$date || $date<'1970-01-01' || $date>'9999-12-31') {
        throw new InvalidArgumentException('Date invalide : utilisez une date réelle au format AAAA-MM-JJ.');
    }
    return $date;
}

function tracking_link(string $url): string
{
    if ($url==='') return '';
    $parts = parse_url($url);
    if (strlen($url)>2000 || !$parts || !filter_var($url,FILTER_VALIDATE_URL) || !in_array(strtolower($parts['scheme']??''),['https','http'],true)
        || isset($parts['user']) || isset($parts['pass']) || preg_match('/[\x00-\x20\x7f]/', $url)) {
        throw new InvalidArgumentException('Les justificatifs et tickets doivent être des liens HTTP(S) sans identifiants.');
    }
    // Internal ticket links are allowed. Never fetch these URLs from the server.
    return $url;
}

function tracking_actor(): string
{
    if (!is_admin()) throw new RuntimeException('Accès administrateur requis.');
    return mb_substr((string)($_SESSION['admin_user'] ?? (getenv('ACTU_ADMIN_USER') ?: 'Administrateur')),0,120);
}

function tracking_case(string $id): ?array
{
    return run('SELECT * FROM cve_cases WHERE cve_id=?',[tracking_id($id)])->fetch() ?: null;
}

function tracking_snapshot(string $id): array
{
    $db=db(); $db->beginTransaction();
    try {
        // One read snapshot pairs the displayed metadata with its signal counter.
        $cve=run('SELECT * FROM cves WHERE id=?',[$id])->fetch();
        $case=tracking_case($id);
        $db->commit(); return [$cve,$case];
    } catch(Throwable $error) { $db->rollBack(); throw $error; }
}

function tracking_event(string $id, string $kind, array $changes, string $note=''): void
{
    run('INSERT INTO cve_events(cve_id,actor,kind,changes,note,created_at) VALUES(?,?,?,?,?,?)',
        [$id,tracking_actor(),$kind,json_encode($changes,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$note,time()]);
}

function tracking_create(string $id): bool
{
    tracking_actor(); $id=tracking_id($id); $db=db(); $db->exec('BEGIN IMMEDIATE');
    try {
        if (tracking_case($id)) { $db->exec('COMMIT'); return false; }
        run('INSERT OR IGNORE INTO cves(id) VALUES(?)',[$id]);
        run('INSERT INTO cve_cases(cve_id,created_at,updated_at) VALUES(?,?,?)',[$id,time(),time()]);
        tracking_event($id,'created',['status'=>['','analyze']]);
        $db->exec('COMMIT'); return true;
    } catch(Throwable $error) { $db->exec('ROLLBACK'); throw $error; }
}

// Every user mutation checks an optimistic revision under one write transaction.
// Source updates use a separate signal counter so they cannot overwrite decisions.
function tracking_mutate(string $id, int $revision, callable $operation): void
{
    tracking_actor(); $id=tracking_id($id); $db=db(); $db->exec('BEGIN IMMEDIATE');
    try {
        $old=tracking_case($id);
        if (!$old) throw new InvalidArgumentException('Cette CVE n’est pas encore suivie.');
        if ($revision!==(int)$old['revision']) throw new TrackingConflict('La fiche a été modifiée depuis son ouverture. Rechargez-la avant de réessayer ; votre saisie n’a pas été enregistrée.');
        if ($operation($old)!==false) run('UPDATE cve_cases SET revision=revision+1,updated_at=? WHERE cve_id=?',[time(),$id]);
        $db->exec('COMMIT');
    } catch(Throwable $error) { $db->exec('ROLLBACK'); throw $error; }
}

function tracking_save(string $id, int $revision, array $data): void
{
    $id=tracking_id($id);
    $status=input('status','',$data); $priority=input('priority','',$data);
    if (!isset(tracking_statuses()[$status]) || !isset(tracking_priorities()[$priority])) throw new InvalidArgumentException('Statut ou priorité invalide.');
    $values=['status'=>$status,'priority'=>$priority,'owner'=>tracking_text($data,'owner',120),
        'due_date'=>tracking_date(input('due_date','',$data)), 'review_date'=>tracking_date(input('review_date','',$data)),
        'scope'=>tracking_text($data,'scope',6000),'mitigation'=>tracking_text($data,'mitigation',6000),
        'decision'=>tracking_text($data,'decision',4000), 'evidence_url'=>tracking_link(input('evidence_url','',$data)),
        'ticket_url'=>tracking_link(input('ticket_url','',$data))];
    $note=tracking_text($data,'change_note',2000);
    if (in_array($status,['fixed','unaffected','accepted'],true) && $values['decision']==='') throw new InvalidArgumentException('Une justification est obligatoire pour cette décision.');
    if ($status==='accepted' && ($values['owner']==='' || $values['review_date']==='' || $values['review_date']<date('Y-m-d'))) {
        throw new InvalidArgumentException('Un risque accepté exige un responsable et une date de réexamen aujourd’hui ou dans le futur.');
    }
    tracking_mutate($id,$revision,function(array $old) use($id,$values,$note): bool {
        if (in_array($values['status'],['fixed','unaffected'],true) && run("SELECT 1 FROM cve_tasks WHERE cve_id=? AND status='todo'",[$id])->fetchColumn()) {
            throw new InvalidArgumentException('Terminez ou annulez les actions ouvertes avant de clôturer la fiche.');
        }
        if ($old['status']!==$values['status'] && in_array($old['status'],['fixed','unaffected','accepted'],true) && $note==='') {
            throw new InvalidArgumentException('Expliquez le changement de décision dans le motif du changement.');
        }
        $changes=[]; foreach($values as $key=>$value) if($old[$key]!==$value) $changes[$key]=[$old[$key],$value];
        if (!$changes) return false;
        $closed=in_array($values['status'],['fixed','unaffected'],true) ? ($old['closed_at'] ?? time()) : null;
        run('UPDATE cve_cases SET '.implode(',',array_map(fn($key)=>$key.'=?',array_keys($values))).',closed_at=? WHERE cve_id=?',[...array_values($values),$closed,$id]);
        tracking_event($id,'updated',$changes,$note); return true;
    });
}

function tracking_comment(string $id, int $revision, string $text): void
{
    $id=tracking_id($id);
    $text=tracking_text(['comment'=>$text],'comment',6000,true);
    tracking_mutate($id,$revision,function() use($id,$text): void { tracking_event($id,'comment',[],$text); });
}

function tracking_task(string $id, int $revision, array $data): void
{
    $id=tracking_id($id);
    $taskId=filter_var(input('task_id','0',$data),FILTER_VALIDATE_INT);
    if($taskId===false || $taskId<0) throw new InvalidArgumentException('Action invalide.');
    $values=['title'=>tracking_text($data,'title',240,true),'owner'=>tracking_text($data,'owner',120),
        'due_date'=>tracking_date(input('due_date','',$data)),'status'=>input('task_status','todo',$data)];
    if(!in_array($values['status'],['todo','done','cancelled'],true)) throw new InvalidArgumentException('État d’action invalide.');
    $note=tracking_text($data,'change_note',2000);
    tracking_mutate($id,$revision,function(array $old) use($id,$taskId,$values,$note): bool {
        if(in_array($old['status'],['fixed','unaffected'],true)) throw new InvalidArgumentException('Rouvrez la fiche avant de modifier son plan d’actions.');
        $task=$taskId?run('SELECT * FROM cve_tasks WHERE id=? AND cve_id=?',[$taskId,$id])->fetch():null;
        if($taskId && !$task) throw new InvalidArgumentException('Cette action n’appartient pas à la fiche.');
        if($values['status']==='cancelled' && ($task['status']??'')!=='cancelled' && $note==='') throw new InvalidArgumentException('Une annulation doit être justifiée dans le motif du changement.');
        $changes=[]; foreach($values as $key=>$value) if(($task[$key]??'')!==$value) $changes[$key]=[$task[$key]??'',$value];
        if(!$changes) return false;
        if($taskId) run('UPDATE cve_tasks SET title=?,owner=?,due_date=?,status=?,updated_at=? WHERE id=? AND cve_id=?',[...array_values($values),time(),$taskId,$id]);
        else { run('INSERT INTO cve_tasks(cve_id,title,owner,due_date,status,created_at,updated_at) VALUES(?,?,?,?,?,?,?)',[$id,...array_values($values),time(),time()]); $taskId=(int)db()->lastInsertId(); }
        tracking_event($id,$task?'task_updated':'task_created',['task_id'=>$taskId,'fields'=>$changes],$note); return true;
    });
}

function tracking_review(string $id, int $revision, int $signal): void
{
    $id=tracking_id($id);
    tracking_mutate($id,$revision,function(array $old) use($id,$signal): bool {
        if((int)$old['signal_version']!==$signal) throw new TrackingConflict('De nouvelles informations viennent d’arriver. Rechargez la fiche avant de confirmer votre examen.');
        if((int)$old['reviewed_signal']===$signal) return false;
        run('UPDATE cve_cases SET reviewed_signal=? WHERE cve_id=?',[$signal,$id]);
        tracking_event($id,'reviewed',['signal'=>[(int)$old['reviewed_signal'],$signal]]); return true;
    });
}

function tracking_signal(string $id): void
{
    run('UPDATE cve_cases SET signal_version=signal_version+1 WHERE cve_id=?',[$id]);
}

function tracking_metadata_changed(string $id, array|false $old): void
{
    $current=run('SELECT * FROM cves WHERE id=?',[$id])->fetch();
    if($old && $current && $old!==$current) tracking_signal($id);
}

function tracking_filters(?array $data=null): array
{
    $data ??= $_GET;
    $status=input('status','',$data); $priority=input('priority','',$data); $sort=input('sort','priority',$data);
    return ['q'=>mb_substr(input('q','',$data),0,200),'owner'=>mb_substr(input('owner','',$data),0,120),
        'status'=>in_array($status,['','untracked','open',...array_keys(tracking_statuses())],true)?$status:'',
        'priority'=>isset(tracking_priorities()[$priority])?$priority:'',
        'kev'=>input('kev','',$data)==='1'?'1':'','overdue'=>input('overdue','',$data)==='1'?'1':'',
        'review'=>input('review','',$data)==='1'?'1':'','soon'=>input('soon','',$data)==='1'?'1':'',
        'sort'=>in_array($sort,['priority','deadline','updated','cvss'],true)?$sort:'priority'];
}

function tracking_overdue_sql(): string
{
    $today=db()->quote(date('Y-m-d'));
    return "((w.status IN ('analyze','affected','progress') AND w.due_date<>'' AND w.due_date<$today) OR COALESCE(t.overdue,0)>0)";
}

function tracking_review_sql(): string
{
    return "(w.signal_version>w.reviewed_signal OR (w.status='accepted' AND w.review_date<>'' AND w.review_date<=".db()->quote(date('Y-m-d'))."))";
}

function tracking_query(array $f): array
{
    $today=db()->quote(date('Y-m-d'));
    $from=" FROM cves c LEFT JOIN cve_cases w ON w.cve_id=c.id LEFT JOIN (
        SELECT cve_id,COUNT(*) AS task_count,SUM(status='todo') AS pending,
        SUM(status='todo' AND due_date<>'' AND due_date<$today) AS overdue,
        MIN(CASE WHEN status='todo' AND due_date<>'' THEN due_date END) AS next_due
        FROM cve_tasks GROUP BY cve_id) t ON t.cve_id=c.id";
    $where=['1=1']; $params=[];
    if($f['q']!=='') { $where[]="instr(actu_lower(c.id||' '||c.vendor||' '||c.product||' '||COALESCE(w.scope,'')),actu_lower(?))>0"; $params[]=$f['q']; }
    if($f['owner']!=='') { $where[]="(instr(actu_lower(COALESCE(w.owner,'')),actu_lower(?))>0 OR EXISTS(SELECT 1 FROM cve_tasks ot WHERE ot.cve_id=c.id AND ot.status='todo' AND instr(actu_lower(ot.owner),actu_lower(?))>0))"; array_push($params,$f['owner'],$f['owner']); }
    if($f['status']==='untracked') $where[]='w.cve_id IS NULL';
    elseif($f['status']==='open') $where[]="w.status IN ('analyze','affected','progress','accepted')";
    elseif($f['status']!=='') { $where[]='w.status=?'; $params[]=$f['status']; }
    if($f['priority']!=='') { $where[]='w.priority=?'; $params[]=$f['priority']; }
    if($f['kev']==='1') $where[]='c.exploited=1';
    if($f['overdue']==='1') $where[]=tracking_overdue_sql();
    if($f['review']==='1') $where[]=tracking_review_sql();
    if($f['soon']==='1') {
        $end=db()->quote(date('Y-m-d',strtotime('+7 days')));
        $where[]="((w.status IN ('analyze','affected','progress') AND w.due_date BETWEEN $today AND $end) OR (w.status='accepted' AND w.review_date BETWEEN $today AND $end) OR t.next_due BETWEEN $today AND $end)";
    }
    return [$from.' WHERE '.implode(' AND ',$where),$params];
}

function tracking_select(): string
{
    return 'SELECT c.*,w.status,w.priority,w.owner,w.due_date AS internal_due,w.review_date,w.scope,w.mitigation,w.decision,w.evidence_url,w.ticket_url,w.revision,w.updated_at,w.created_at,w.closed_at,w.signal_version,w.reviewed_signal,COALESCE(t.pending,0) AS pending,t.next_due,'.tracking_overdue_sql().' AS is_overdue,'.tracking_review_sql().' AS needs_review';
}

function tracking_order(array $f): string
{
    return match($f['sort']) {
        'deadline'=>"CASE WHEN w.status='accepted' THEN NULLIF(w.review_date,'') ELSE NULLIF(w.due_date,'') END IS NULL, CASE WHEN w.status='accepted' THEN NULLIF(w.review_date,'') ELSE NULLIF(w.due_date,'') END,t.next_due,c.id",
        'updated'=>'w.updated_at DESC,c.id', 'cvss'=>'c.cvss DESC,c.exploited DESC,c.id',
        default=>"CASE WHEN w.status IN ('fixed','unaffected') THEN 1 ELSE 0 END,CASE w.priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 WHEN 'low' THEN 3 ELSE 4 END,c.exploited DESC,c.cvss DESC,c.id",
    };
}

function tracking_csv_cell(mixed $value): string
{
    $text=(string)($value??'');
    return preg_match('/^[\s\x00-\x1f]*[=+@-]/u',$text)?"'".$text:$text;
}
