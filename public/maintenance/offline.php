<?php
    if (!file_exists(__DIR__ . '/.active')) {
        header("Location: /");
        die;
    }

    header('X-AJAX-Control: reset');

    $f = function(string $ff,$type): string { return "url(data:image/{$type};base64," . base64_encode( file_get_contents( __DIR__ . '/' . $ff ) ) . ')'; }
?>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta charset="UTF-8">

    <!-- Keep the robots out -->
    <meta name="robots" content="noindex, nofollow">

    <!-- Set the base URL to account for virtual mod_rewrite URLs -->
    <base target="_blank" href="/myhordes">

    <!-- Set page title -->
    <title>MyHordes - System Maintenance</title>

    <style>
        body { background: <?=$f('offline_back_center.png','png')?>; background-size: contain; font-family: sans-serif; }
        #header, #content, #footer { position: relative; margin: 0 auto; padding: 0; width: 950px; overflow: visible; }
        #header  { height: 140px; background: <?=$f('deco_header.png','png')?> left top no-repeat; }
        #content { background: <?=$f('bg_content.jpg','jpeg')?> left top repeat-y }
        #footer  { height: 14px; background: <?=$f('bg_content_footer.gif','gif')?> left top no-repeat }

        #button { position: absolute; height: 46px; width: 137px; top: 51px; left: 409px; cursor: pointer;
            background: <?=$f('deco_jouerBt.gif','gif')?> center no-repeat;
        }
        #sparks { position: absolute; height: 38px; width: 166px; top: 6px; left: 395px; cursor: pointer;
            background: <?=$f('electrik.gif','gif')?> center no-repeat;
        }
        #button:hover { background: <?=$f('deco_jouerBt2.gif','gif')?> center no-repeat; }
        #button>div {
            text-align: center; text-transform: uppercase; font-size: 19pt; padding-top: 5px; font-weight: bolder; color: #f0d79e;
            text-shadow: 0 2px 0 #94361b, 0 -2px 0 #94361b, 2px 0 0 #94361b, -2px 0 0 #94361b, 2px 2px 0 #94361b, -2px -2px 0 #94361b, -2px 2px 0 #94361b;
        }
        #button:hover>div { padding-top: 8px; }

        #content-header, #content-content, #content-footer { position: relative; margin: 0 auto; padding: 0; width: 600px; overflow: hidden; }
        #content-header  { height: 18px; background: <?=$f('panel_header.gif','gif')?> left top no-repeat; }
        #content-content { background: <?=$f('panel_bg.gif','gif')?> left top repeat-y; }
        #content-footer  { height: 16px; background: <?=$f('panel_footer.gif','gif')?> left top no-repeat }

        #content-content>div { padding: 2px 20px; }
        h1 { color: #f0d79e; border-bottom: 1px solid #f0d79e; font-size: 1.4em; }
        h1:not(:first-child) { margin: 45px 0 0; }
        p.text { text-align: justify; color: white; padding-left: 48px; }
        p.en { background: <?=$f( 'en.png', 'png')?> left top no-repeat }
        p.fr { background: <?=$f( 'fr.png', 'png')?> left top no-repeat }
        p.de { background: <?=$f( 'de.png', 'png')?> left top no-repeat }
        p.es { background: <?=$f( 'es.png', 'png')?> left top no-repeat }
    </style>
</head>
<body>
    <div id="header">
        <div id="sparks"></div>
        <div id="button" onclick="location.reload();"><div>Reload</div></div>
    </div>
    <div id="content">
        <div id="content-header"></div>
        <div id="content-content">
            <div>
                <h1>Maintenance Mode</h1>
                <p class="text en">
                    <i>MyHordes</i> is currently in <b>Maintenance Mode</b>. This usually happens when an update is being installed. Please stay patient, normal operations will continue shortly.
                    Reload this page or click the button at the top to try again.
                </p>

                <h1>Maintenance en cours</h1>
                <p class="text fr">
                    <i>MyHordes</i> est actuellement en <b>cours de maintenance</b>. Cela se produit généralement lorsqu'une mise à jour est installée. Veuillez patienter un instant, le fonctionnement normal reviendra sûrement sous peu.
                    Rechargez cette page ou cliquez sur le bouton en haut pour réessayer.
                </p>

                <h1>Wartungsmodus</h1>
                <p class="text de">
                    <i>MyHordes</i> befindet sich derzeit im <b>Wartungsmodus</b>. Dies geschieht üblicherweise, wenn ein Update installiert wird. Bitte habe einen Augenblick Geduld, es geht sicher gleich weiter.
                    Lade diese Seite neu oder klicke auf die Schaltfläche ganz oben, um es erneut zu versuchen.
                </p>

                <h1>Modo de Mantenimiento</h1>
                <p class="text es">
                    <i>MyHordes</i> está actualmente en <b>modo de mantenimiento</b>. Esto suele ocurrir cuando se instala una actualización. Por favor, tenga paciencia por un momento, el funcionamiento normal seguramente continuará en breve.
                    Recargue esta página o haga clic en el botón de la parte superior para intentarlo de nuevo.
                </p>
            </div>
        </div>
        <div id="content-footer"></div>

    </div>
    <div id="footer"></div>
</body>
</html>
