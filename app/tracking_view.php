<?php
function tracking_revision_field(array $case): string
{
    return '<input type="hidden" name="revision" value="'.(int)$case['revision'].'">';
}

function tracking_badges(array $row): void
{
    ?><span class="badge"><?=e(tracking_statuses()[$row['status']??'']??'Non suivi')?></span>
    <?php if(!empty($row['priority'])): ?><span class="badge <?=$row['priority']==='urgent'?'danger':''?>">Priorité <?=e(mb_strtolower(tracking_priorities()[$row['priority']]))?></span><?php endif ?>
    <?php if(!empty($row['is_overdue'])): ?><span class="badge danger">En retard</span><?php endif ?>
    <?php if(!empty($row['needs_review'])): ?><span class="badge warning">À réexaminer</span><?php endif ?>
    <?php
}

function tracking_pagination(string $key, int $page, int $pages, array $query, string $fragment=''): void
{
    if($pages<2) return;
    ?><nav class="pagination" aria-label="<?=e('Pagination '.$key)?>">
    <?php if($page>1): ?><a href="?<?=e(http_build_query(array_merge($query,[$key=>$page-1])))?><?=e($fragment)?>">← Précédent</a><?php endif ?>
    <span>Page <?=$page?> / <?=$pages?></span>
    <?php if($page<$pages): ?><a href="?<?=e(http_build_query(array_merge($query,[$key=>$page+1])))?><?=e($fragment)?>">Suivant →</a><?php endif ?></nav><?php
}

function tracking_history_value(string $field, mixed $value): string
{
    $value=(string)$value;
    if($field==='status') return tracking_statuses()[$value]??(['todo'=>'À faire','done'=>'Terminée','cancelled'=>'Annulée'][$value]??$value);
    if($field==='priority') return tracking_priorities()[$value]??$value;
    return $value===''?'—':$value;
}

function tracking_history(array $events): void
{
    $labels=['created'=>'Suivi ouvert','updated'=>'Fiche modifiée','comment'=>'Note ajoutée','reviewed'=>'Informations examinées','task_created'=>'Action créée','task_updated'=>'Action modifiée'];
    foreach($events as $event): $changes=json_decode($event['changes'],true,32,JSON_THROW_ON_ERROR); ?>
    <article class="case-event"><div class="card-meta"><strong><?=e($labels[$event['kind']]??$event['kind'])?></strong><span><?=e($event['actor'])?></span><time datetime="<?=e(date(DATE_ATOM,(int)$event['created_at']))?>"><?=e(when((int)$event['created_at']))?></time></div>
    <?php if($event['note']!==''): ?><p class="preserve-lines"><?=e($event['note'])?></p><?php endif ?>
    <?php if(isset($changes['task_id'])): ?><p>Action #<?=(int)$changes['task_id']?></p><?php $changes=$changes['fields']; endif ?>
    <?php if($changes): ?><details><summary>Voir les changements</summary><dl class="case-changes">
    <?php foreach($changes as $key=>$pair): ?><dt><?=e(tracking_fields()[$key]??(['title'=>'Action','signal'=>'Version des informations examinées'][$key]??$key))?></dt><dd><span class="muted">Avant :</span> <span class="preserve-lines"><?=e(tracking_history_value($key,$pair[0]))?></span><br><span class="muted">Après :</span> <span class="preserve-lines"><?=e(tracking_history_value($key,$pair[1]))?></span></dd><?php endforeach ?>
    </dl></details><?php endif ?></article><?php endforeach;
}
