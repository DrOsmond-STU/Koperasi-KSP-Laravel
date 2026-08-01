<?php

namespace App\Support\Installer;

/**
 * Halaman installer dirender sebagai HTML mentah lewat class ini, BUKAN
 * lewat Blade/view() — AppServiceProvider mendaftarkan
 * `View::composer('*', ...)` yang query tabel `app_branding_settings` di
 * setiap render view, dan installer harus tetap bisa tampil sebelum DB
 * dikonfigurasi/dimigrasi sama sekali. Jadi seluruh alur installer sengaja
 * lepas total dari Blade supaya tidak bergantung pada state DB.
 */
class InstallerPage
{
    /** @var array<string, string> */
    private const STEPS = [
        'welcome' => 'Persyaratan',
        'database' => 'Database & Aplikasi',
        'migrate' => 'Migrasi & Storage',
        'admin' => 'Akun Admin',
        'finish' => 'Selesai',
    ];

    public static function render(string $activeStep, string $heading, string $bodyHtml): string
    {
        $appName = e(config('app.name') ?: 'Koperasi');
        $nav = self::renderSteps($activeStep);

        return <<<HTML
        <!doctype html>
        <html lang="id">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Instalasi — {$appName}</title>
            <style>
                :root {
                    --pine: #11543B; --pine-deep: #0C3B29; --pine-ink: #16201C;
                    --gold: #C68A2E; --ok: #2E7D52; --brick: #A8472F;
                    --paper: #F4F7F4; --surface: #FFFFFF; --line: #D7E2DB; --muted: #5C6E64;
                }
                * { box-sizing: border-box; }
                body {
                    margin: 0; font-family: 'Segoe UI', Inter, system-ui, sans-serif; color: var(--pine-ink);
                    background: var(--paper); min-height: 100vh;
                }
                .wrap { max-width: 720px; margin: 0 auto; padding: 32px 20px 60px; }
                .brand { text-align: center; margin-bottom: 24px; }
                .brand h1 { font-size: 18px; margin: 0 0 4px; }
                .brand p { margin: 0; color: var(--muted); font-size: 13px; }
                .steps { display: flex; gap: 6px; margin-bottom: 24px; flex-wrap: wrap; justify-content: center; }
                .step {
                    font-size: 11px; font-weight: 700; padding: 6px 10px; border-radius: 20px;
                    background: var(--surface); border: 1px solid var(--line); color: var(--muted);
                }
                .step.active { background: var(--pine); border-color: var(--pine); color: #fff; }
                .step.done { background: var(--gold-soft, #F3E6CC); border-color: var(--gold); color: var(--pine-deep); }
                .card {
                    background: var(--surface); border: 1px solid var(--line); border-radius: 14px;
                    box-shadow: 0 1px 2px rgba(16,32,25,.04), 0 20px 40px -20px rgba(16,32,25,.18);
                    padding: 28px;
                }
                .card h2 { margin-top: 0; font-size: 17px; }
                .card p.lead { color: var(--muted); font-size: 13px; margin-top: -6px; }
                .alert { padding: 12px 14px; border-radius: 9px; font-size: 13px; margin-bottom: 18px; }
                .alert-error { background: #FBEAE6; color: var(--brick); border: 1px solid #EBC3B6; }
                .alert-success { background: #E7F3EC; color: var(--ok); border: 1px solid #BFE0CC; }
                table.checks { width: 100%; border-collapse: collapse; margin: 16px 0; font-size: 13px; }
                table.checks th { text-align: left; font-size: 11px; text-transform: uppercase; color: var(--muted); padding: 6px 8px; border-bottom: 1px solid var(--line); }
                table.checks td { padding: 8px; border-bottom: 1px solid var(--line); vertical-align: top; }
                table.checks tr:last-child td { border-bottom: none; }
                .badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 700; }
                .badge-ok { background: #E7F3EC; color: var(--ok); }
                .badge-fail { background: #FBEAE6; color: var(--brick); }
                .badge-warn { background: var(--gold-soft, #F3E6CC); color: #8A5A18; }
                .muted { color: var(--muted); }
                .field { margin-bottom: 16px; }
                .field label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
                .field input, .field textarea, .field select {
                    width: 100%; padding: 10px 12px; border: 1px solid var(--line);
                    border-radius: 9px; font-size: 14px; font-family: inherit; background: #fff; color: var(--pine-ink);
                }
                .field .help { font-size: 11px; color: var(--muted); margin-top: 4px; }
                .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0 16px; }
                fieldset { border: 1px solid var(--line); border-radius: 10px; padding: 14px 16px; margin: 0 0 20px; }
                legend { font-size: 12px; font-weight: 700; color: var(--pine); padding: 0 6px; }
                .btn-primary {
                    padding: 11px 20px; background: var(--pine); color: #fff; border: none;
                    border-radius: 9px; font-weight: 700; font-size: 14px; cursor: pointer; text-decoration: none; display: inline-block;
                }
                .btn-primary:hover { background: var(--pine-deep); }
                .btn-primary[disabled], .btn-primary.disabled {
                    background: var(--muted); cursor: not-allowed; pointer-events: none; opacity: .6;
                }
                .btn-row { margin-top: 20px; display: flex; justify-content: flex-end; gap: 10px; }
                code, pre {
                    background: var(--paper); border: 1px solid var(--line); border-radius: 6px;
                    padding: 2px 6px; font-size: 12px;
                }
                pre { padding: 12px; overflow-x: auto; }
                ul.checklist { list-style: none; padding: 0; margin: 16px 0; font-size: 13px; }
                ul.checklist li { padding: 6px 0 6px 26px; position: relative; }
                ul.checklist li::before { content: "\\2713"; position: absolute; left: 4px; color: var(--ok); font-weight: 700; }
            </style>
        </head>
        <body>
            <div class="wrap">
                <div class="brand">
                    <h1>Instalasi {$appName}</h1>
                    <p>Wizard setup otomatis — cocok untuk shared hosting/cPanel</p>
                </div>
                <div class="steps">{$nav}</div>
                <div class="card">
                    <h2>{$heading}</h2>
                    {$bodyHtml}
                </div>
            </div>
        </body>
        </html>
        HTML;
    }

    private static function renderSteps(string $activeStep): string
    {
        $keys = array_keys(self::STEPS);
        $activeIndex = array_search($activeStep, $keys, true);
        $html = '';

        foreach (array_values(self::STEPS) as $i => $label) {
            $class = 'step';
            if ($i === $activeIndex) {
                $class .= ' active';
            } elseif ($activeIndex !== false && $i < $activeIndex) {
                $class .= ' done';
            }
            $html .= '<span class="'.$class.'">'.($i + 1).'. '.e($label).'</span>';
        }

        return $html;
    }

    public static function alert(?string $message, string $type = 'error'): string
    {
        if (! $message) {
            return '';
        }

        $class = $type === 'error' ? 'alert-error' : 'alert-success';

        return '<div class="alert '.$class.'">'.nl2br(e($message)).'</div>';
    }

    public static function csrfField(): string
    {
        return '<input type="hidden" name="_token" value="'.e(csrf_token()).'">';
    }

    public static function field(
        string $label,
        string $name,
        string $type = 'text',
        string $value = '',
        bool $required = true,
        string $help = '',
        string $placeholder = '',
    ): string {
        $help = $help !== '' ? '<div class="help">'.e($help).'</div>' : '';
        $requiredAttr = $required ? ' required' : '';
        $placeholderAttr = $placeholder !== '' ? ' placeholder="'.e($placeholder).'"' : '';

        return '<div class="field">'
            .'<label for="'.e($name).'">'.e($label).'</label>'
            .'<input id="'.e($name).'" type="'.e($type).'" name="'.e($name).'" value="'.e($value).'"'.$requiredAttr.$placeholderAttr.'>'
            .$help
            .'</div>';
    }
}
