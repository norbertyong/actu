<?php
declare(strict_types=1);

function setting(string $name, string $default = ''): string
{
    $value = run('SELECT value FROM settings WHERE name=?', [$name])->fetchColumn();
    return $value === false ? $default : (string) $value;
}

function set_setting(string $name, string $value): void
{
    run('INSERT INTO settings VALUES(?,?) ON CONFLICT(name) DO UPDATE SET value=excluded.value', [$name, $value]);
}

// Literal substring matching, including Unicode; never interpret SQL wildcards.
// a must refer to an article. Product names from KEV complement feed text.
function technology_match_sql(): string
{
    return "EXISTS(SELECT 1 FROM technologies t WHERE t.reader=? AND (
        instr(actu_lower(a.title || ' ' || a.summary),actu_lower(t.name))>0 OR
        EXISTS(SELECT 1 FROM article_cves tc JOIN cves cv ON cv.id=tc.cve_id
            WHERE tc.article_id=a.id AND instr(actu_lower(cv.vendor || ' ' || cv.product),actu_lower(t.name))>0)))";
}

function add_technology(string $reader, string $name): void
{
    $name = trim($name);
    if (mb_strlen($name) < 2 || mb_strlen($name) > 80 || preg_match('/[\x00-\x1f]/', $name)) {
        throw new InvalidArgumentException('Saisissez un produit ou une expression de 2 à 80 caractères.');
    }
    if (run('SELECT 1 FROM technologies WHERE reader=? AND actu_lower(name)=?', [$reader, mb_strtolower($name)])->fetchColumn()) return;
    if ((int) run('SELECT COUNT(*) FROM technologies WHERE reader=?', [$reader])->fetchColumn() >= 30) {
        throw new InvalidArgumentException('La limite est de 30 technologies par navigateur.');
    }
    run('INSERT INTO technologies(reader,name) VALUES(?,?)', [$reader, $name]);
}

function source_health(array $source, ?int $now = null): array
{
    $now ??= time();
    if (!$source['enabled']) return ['Désactivée', 'muted'];
    if ($source['error']) return ['Collecte en erreur', 'danger'];
    if (!$source['success_at']) return ['Jamais collectée', 'warning'];
    if ((int) $source['success_at'] < $now - 3600) return ['Collecte en retard', 'warning'];
    if (empty($source['last_article']) || (int) $source['last_article'] < $now - 7 * 86400) {
        return ['Aucune publication récente', 'warning'];
    }
    return ['À jour', ''];
}

function collector_event(string $event, string $value): void
{
    set_setting('collector_cli_' . $event, $value);
}
