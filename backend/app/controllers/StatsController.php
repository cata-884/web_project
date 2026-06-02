<?php
use JetBrains\PhpStorm\NoReturn;

class StatsController extends Controller
{
    private StatsService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new StatsService(new StatsRepository());
    }

    #[NoReturn]
    public function summary(): void
    {
        $this->requireAdmin();
        $this->json($this->service->getSummary());
    }

    #[NoReturn]
    public function chartSvg(): void
    {
        $this->requireAdmin();
        $type = $_GET['type'] ?? 'bookings_per_month';
        $data = $this->service->getChartData($type);

        header('Content-Type: image/svg+xml; charset=utf-8');
        echo $this->buildBarChart($data['rows'], $data['title']);
        exit();
    }

    #[NoReturn]
    public function reportPdf(): void
    {
        $this->requireAdmin();

        if (!class_exists('\Dompdf\Dompdf')) {
            $this->json(['error' => 'dompdf nu este instalat. Ruleaza composer install in container.'], 503);
        }

        $data        = $this->service->getReportData();
        $today       = date('d.m.Y');
        $nr_users    = $data['nr_users'];
        $nr_campings = $data['nr_campings'];
        $nr_pending  = $data['nr_pending'];
        $nr_bookings = $data['nr_bookings'];
        $total_rev   = $data['total_rev'];

        $topRows = '';
        foreach ($data['top_campings'] as $i => $c) {
            $topRows .= "<tr><td>" . ($i+1) . "</td><td>" . htmlspecialchars($c['name']) . "</td>"
                      . "<td>" . $c['avg_rating'] . "</td><td>" . $c['nr_reviews'] . "</td></tr>";
        }

        $html = <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8">
<style>
  body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1f2937; margin: 0; padding: 24px; }
  h1 { font-size: 22px; color: #D97706; margin-bottom: 4px; }
  .sub { color: #6b7280; font-size: 11px; margin-bottom: 24px; }
  .grid { display: table; width: 100%; margin-bottom: 24px; }
  .card { display: table-cell; padding: 12px 16px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; width: 22%; text-align: center; }
  .card-val { font-size: 28px; font-weight: 700; color: #D97706; }
  .card-lbl { font-size: 10px; color: #6b7280; margin-top: 4px; }
  table { width: 100%; border-collapse: collapse; margin-top: 16px; }
  th { background: #D97706; color: #fff; padding: 7px 10px; text-align: left; font-size: 11px; }
  td { padding: 6px 10px; border-bottom: 1px solid #e5e7eb; font-size: 11px; }
  tr:nth-child(even) td { background: #f9fafb; }
  h2 { font-size: 14px; color: #374151; margin-top: 28px; margin-bottom: 8px; border-bottom: 2px solid #D97706; padding-bottom: 4px; }
  .footer { margin-top: 40px; text-align: center; color: #9ca3af; font-size: 10px; }
</style></head><body>
<h1>Raport Platforma CaT</h1>
<div class="sub">Generat la: $today</div>
<div class="grid">
  <div class="card"><div class="card-val">$nr_users</div><div class="card-lbl">Utilizatori</div></div>
  <div class="card"><div class="card-val">$nr_campings</div><div class="card-lbl">Campinguri Active</div></div>
  <div class="card"><div class="card-val">$nr_pending</div><div class="card-lbl">In Asteptare</div></div>
  <div class="card"><div class="card-val">$nr_bookings</div><div class="card-lbl">Rezervari Total</div></div>
</div>
<p><strong>Venituri confirmate:</strong> <span style="color:#059669;font-size:16px;font-weight:700;">{$this->formatMoney($total_rev)} RON</span></p>
<h2>Top Campinguri dupa Rating</h2>
<table><tr><th>#</th><th>Camping</th><th>Rating Mediu</th><th>Recenzii</th></tr>$topRows</table>
<div class="footer">CaT Camping Info &mdash; Raport automat</div>
</body></html>
HTML;

        $options = new \Dompdf\Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="raport-cat-' . date('Y-m-d') . '.pdf"');
        echo $dompdf->output();
        exit();
    }

    private function buildBarChart(array $rows, string $title): string
    {
        $W = 700; $H = 340; $padL = 54; $padR = 16; $padT = 48; $padB = 60;
        $chartW = $W - $padL - $padR; $chartH = $H - $padT - $padB;

        if (!$rows) {
            return "<svg xmlns='http://www.w3.org/2000/svg' width='$W' height='$H'>"
                 . "<text x='" . ($W/2) . "' y='" . ($H/2) . "' text-anchor='middle' fill='#888' font-size='14'>Fara date</text>"
                 . "</svg>";
        }

        $values = array_column($rows, 'value');
        $maxVal = max($values) ?: 1;
        $n = count($rows); $barW = max(8, ($chartW/$n)*0.6); $gap = $chartW/$n;
        $bars = ''; $xLabels = ''; $yStep = $this->niceStep($maxVal, 5);

        for ($i = 0; $i < $n; $i++) {
            $val = (float)$rows[$i]['value'];
            $lbl = htmlspecialchars($rows[$i]['label'], ENT_XML1);
            $bH  = $chartH * ($val / $maxVal);
            $x   = $padL + $gap * $i + $gap / 2 - $barW / 2;
            $y   = $padT + $chartH - $bH;
            $bars    .= "<rect x='" . round($x,1) . "' y='" . round($y,1) . "' width='" . round($barW,1) . "' height='" . round($bH,1) . "' rx='3' fill='#D97706' opacity='0.85'/>";
            $bars    .= "<text x='" . round($x+$barW/2,1) . "' y='" . round($y-4,1) . "' text-anchor='middle' font-size='10' fill='#555'>" . ($val >= 1000 ? round($val/1000,1).'k' : round($val)) . "</text>";
            $xLabels .= "<text x='" . round($x+$barW/2,1) . "' y='" . ($padT+$chartH+16) . "' text-anchor='middle' font-size='10' fill='#666'>$lbl</text>";
        }

        $yLines = '';
        for ($v = 0; $v <= $maxVal; $v += $yStep) {
            $y      = $padT + $chartH - $chartH * ($v / $maxVal);
            $label  = $v >= 1000 ? round($v/1000,1).'k' : $v;
            $yLines .= "<line x1='$padL' x2='" . ($W-$padR) . "' y1='" . round($y,1) . "' y2='" . round($y,1) . "' stroke='#e5e7eb' stroke-width='1'/>";
            $yLines .= "<text x='" . ($padL-6) . "' y='" . round($y+4,1) . "' text-anchor='end' font-size='10' fill='#888'>$label</text>";
        }

        return "<?xml version='1.0' encoding='UTF-8'?>"
             . "<svg xmlns='http://www.w3.org/2000/svg' width='$W' height='$H' style='font-family:sans-serif;background:#fff'>"
             . "<text x='" . ($W/2) . "' y='28' text-anchor='middle' font-size='14' font-weight='600' fill='#1f2937'>" . htmlspecialchars($title, ENT_XML1) . "</text>"
             . $yLines . $bars . $xLabels
             . "<line x1='$padL' y1='$padT' x2='$padL' y2='" . ($padT+$chartH) . "' stroke='#9ca3af' stroke-width='1.5'/>"
             . "<line x1='$padL' y1='" . ($padT+$chartH) . "' x2='" . ($W-$padR) . "' y2='" . ($padT+$chartH) . "' stroke='#9ca3af' stroke-width='1.5'/>"
             . "</svg>";
    }

    private function niceStep(float $maxVal, int $steps): float
    {
        $raw  = $maxVal / $steps;
        $mag  = pow(10, floor(log10(max($raw, 1))));
        return max(1, ceil($raw / $mag) * $mag);
    }

    private function formatMoney(float $v): string
    {
        return number_format($v, 2, ',', '.');
    }
}
