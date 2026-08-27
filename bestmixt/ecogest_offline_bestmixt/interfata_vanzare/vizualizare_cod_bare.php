<?php
include('session.php');

$c_bare = (string)($_SESSION['c_bare'] ?? '');

function code128_svg(string $text): string
{
    $patterns = [
        '212222','222122','222221','121223','121322','131222','122213','122312','132212','221213',
        '221312','231212','112232','122132','122231','113222','123122','123221','223211','221132',
        '221231','213212','223112','312131','311222','321122','321221','312212','322112','322211',
        '212123','212321','232121','111323','131123','131321','112313','132113','132311','211313',
        '231113','231311','112133','112331','132131','113123','113321','133121','313121','211331',
        '231131','213113','213311','213131','311123','311321','331121','312113','312311','332111',
        '314111','221411','431111','111224','111422','121124','121421','141122','141221','112214',
        '112412','122114','122411','142112','142211','241211','221114','413111','241112','134111',
        '111242','121142','121241','114212','124112','124211','411212','421112','421211','212141',
        '214121','412121','111143','111341','131141','114113','114311','411113','411311','113141',
        '114131','311141','411131','211412','211214','211232','2331112'
    ];

    $codes = [104];
    $checksum = 104;
    $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
    foreach ($chars as $index => $char) {
        $ord = ord($char);
        if ($ord < 32 || $ord > 126) {
            $ord = 32;
        }
        $code = $ord - 32;
        $codes[] = $code;
        $checksum += $code * ($index + 1);
    }
    $codes[] = $checksum % 103;
    $codes[] = 106;

    $module = 2;
    $height = 90;
    $quiet = 20;
    $x = $quiet;
    $rects = '';

    foreach ($codes as $code) {
        $pattern = $patterns[$code] ?? '';
        $bar = true;
        foreach (str_split($pattern) as $width) {
            $w = ((int)$width) * $module;
            if ($bar) {
                $rects .= "<rect x=\"$x\" y=\"0\" width=\"$w\" height=\"$height\" fill=\"#000\"/>";
            }
            $x += $w;
            $bar = !$bar;
        }
    }

    $svgWidth = $x + $quiet;
    return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $svgWidth . '" height="130" viewBox="0 0 ' . $svgWidth . ' 130" role="img" aria-label="Cod bare">'
        . '<rect width="100%" height="100%" fill="#fff"/>'
        . $rects
        . '<text x="50%" y="118" font-family="Arial, sans-serif" font-size="18" text-anchor="middle">'
        . htmlspecialchars($text, ENT_QUOTES, 'UTF-8')
        . '</text></svg>';
}
?>
<center>
    <?php echo code128_svg($c_bare); ?>
    <br>
    <button id="back" onclick="goBack()">Inapoi</button>
    <button id="show_button">Listeaza</button>
</center>

<script>
function goBack() {
    window.history.back();
}

var button = document.getElementById('show_button');
button.addEventListener('click', hideshow, false);

function hideshow() {
    this.style.display = 'none';
    document.getElementById('back').style.display = 'none';
    window.print();
}
</script>
