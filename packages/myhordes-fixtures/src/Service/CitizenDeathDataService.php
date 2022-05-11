<?php

namespace MyHordes\Fixtures\Service;

use App\Entity\CauseOfDeath;
use MyHordes\Fixtures\Interfaces\FixtureProcessorInterface;

class CitizenDeathDataService implements FixtureProcessorInterface {

    public function process(array &$data): void
    {
        $data = array_merge_recursive($data, [
            [
                'ref' => CauseOfDeath::Unknown,
                'label' => 'Unbekannte Todesursache',
                'icon' => 'unknown',
                'desc' => 'Du weist nicht, was gerade geschehen ist... plötzlich warst du einfach nicht mehr am leben.',
            ],
            [
                'ref' => CauseOfDeath::NightlyAttack,
                'label' => 'Zombieangriff',
                'icon' => 'die2nite',
                'desc' => 'Du hattest dich daheim eingebunkert und dachtest so überleben zu können... Als sie vor deiner Türe grunzten und schnüffelten, warst du dann mucksmäuschenstill. Leider hattest du ein Fenster nicht ausreichend vernagelt, sodass sie schließlich in dein Wohnzimmer stolperten. Vor Angst erstarrt hast du dann keinen Ton mehr rausgebracht und musstest ansehen, wie sie dich ins Schlafzimmer zerrten, um sich an deinen inneren Organen zu laben und dich langsam zu verspeisen.',
                'pictos' => ['r_dcity_#00']
            ],
            [
                'ref' => CauseOfDeath::Vanished,
                'label' => 'Außerhalb der Stadt verschwunden',
                'icon' => 'vanish',
                'desc' => 'Kurz nach Mitternacht wurde es unheimlich still... so, als ob jegliches Leben mit einem Schlag ausgelöscht worden wäre. Die Nacht kam dir noch eisiger als sonst vor... und plötzlich sahst du sie: Dutzende, hunderte, und alle schwankten auf dich zu! Ihre Hände reckten sich nach dir. Sie ergriffen, zogen und rissen dich auseinander... Dann haben sie dich mitgenommmen und sich an dir bis zum frühen Morgen gütlich getan.',
                'pictos' => ['r_doutsd_#00']
            ],
            [
                'ref' => CauseOfDeath::Dehydration,
                'label' => 'Dehydration',
                'icon' => 'dehydrated',
                'desc' => 'Dehydratation ist wirklich was Übles. Muskelspasmen, Atemprobleme und unaushaltbare Gliederschmerzen treten in der finalen Phase auf. Du hast nach etwas Trinkbarem gesucht - überall.... Vergeblich. Deine Mitbürger behaupten sogar, dich dabei gesehen zu haben, wie du in den letzten Minuten schaufelweise Sand in dich geschüttet hättest... Herrje...',
                'pictos' => ['r_dwater_#00']
            ],
            [
                'ref' => CauseOfDeath::GhulStarved,
                'label' => 'Verhungert',
                'icon' => 'starved',
                'desc' => 'Von Hungerschmerzen geplagt bist du den ganzen Tag durch die Stadt getorkelt... immer auf der Suche nach einem Bissen Fleisch...doch vergebens. Am Ende des Tages haben dich deine Kräfte dann schließlich verlassen. Langsam, ganz langsam, bist du zu Boden gesunken. Selbst deine letzten Atemzüge waren eine Pein... Das Letzte was die Welt noch von dir vernahm war ein unmenschlicher, gellender Schrei gen Himmel und das warst du tot.',
            ],
            [
                'ref' => CauseOfDeath::Addiction,
                'label' => 'Drogenabhängigkeit',
                'icon' => 'addicted',
                'desc' => 'Drogen sind echt was Tolles, solange du genügend hast. Wenn sie dir aber ausgehen, sieht\'s schon anders aus... Schweißausbrüche, Panikattacken, Zittern... du rennst alle zehn Minuten aufs Klo, um dich zu übergeben. Am Ende, wenn du deinen Verstand bereits verloren hast, schluckst du dann Steine, weil du sie mit Steroiden verwechselst.',
                'pictos' => ['r_ddrug_#00']
            ],
            [
                'ref' => CauseOfDeath::Infection,
                'label' => 'Infektion',
                'icon' => 'infection',
                'desc' => 'Stück für Stück hat dich die Krankheit von innen her aufgefressen... Die Infektionssymptome waren auch nicht mehr zu übersehen: eiternde Wunden, Hematome, faulendes Fleisch... Nach mehreren Stunden Leiden hast du den Tod schließlich als Erleichterung empfunden.',
                'pictos' => ['r_dinfec_#00']
            ],
            [
                'ref' => CauseOfDeath::Cyanide,
                'label' => 'Zyanid',
                'icon' => 'cyanide',
                'desc' => 'Du dachtest dir: "Bevor ich noch eine weitere Minute mit diesen Losern verbringe, kürze ich das Ganze ein wenig ab...". Was soll\'s? Einen Tag mehr oder weniger, was macht das schon für einen Unterschied...',
            ],
            [
                'ref' => CauseOfDeath::Poison,
                'label' => 'Ermordung durch Gift',
                'icon' => 'poison',
                'desc' => 'Sorglos hast du dieses Produkt runtergeschluckt... Das darin enthaltene Gift hat nur wenige Sekunden gebraucht, um über dein Blutkreislaufsystem zum Herzen zu gelangen. Du hast dein Bewusstsein verloren. Atemstillstand und Herzversagen haben dir dann den Rest gegeben. Du wurdest vergifet!! Wie hinterhältig!',
            ],
            [
                'ref' => CauseOfDeath::GhulEaten,
                'label' => 'Von einem Ghul gefressen',
                'icon' => 'eaten',
                'desc' => 'Argh!! Es scheint, als ob sich ein Ghul unter die Einwohner gemischt hätte! Völlig ahnungs- und wehrlos wurdest du von etwas oder jemandem angefallen und übel vermöbelt. Als er dann seine fauligen Zähne in deinen Hals schlug warst du noch bei Bewusstsein... Doch du hattest nur noch einen Gedanken: Wer war das?',
            ],
            [
                'ref' => CauseOfDeath::GhulBeaten,
                'label' => 'Aggression',
                'icon' => 'beaten',
                'desc' => 'Du wurdest übel zusammengeschlagen und bist an deinen inneren Verletzungen gestorben... Dir gings eh nicht mehr so gut, dein Immunsystem war bereits geschwächt, da steckt man Schläge nicht mehr so einfach weg. So ein Mist aber auch, mit deiner besonderen Fähigkeit hättest du in deiner Stadt noch ein Weilchen Furcht und Schrecken verbreiten können...',
            ],
            [
                'ref' => CauseOfDeath::Hanging,
                'label' => 'Erhängt',
                'icon' => 'hanged',
                'desc' => 'Ein paar Leute in der Stadt mochten dich ganz offensichtlich nicht, darunter befanden sich mehrere Mitbürger, deine Nachbarn - ja sogar ein paar von deinen Freunden! Aus diesem Grund haben sie dich zu einer Baustelle geschleppt und dir einen Strick um den Hals gelegt. Das ganze ging ruck zuck. Innerhalb weniger Sekunden bist du einen Meter über den Boden geschwebt. Nach einem kurzen Applaus löste sich die Gruppe auf und jeder kehrte zu seiner Arbeit zurück.',
                'pictos' => ['r_dhang_#00']
            ],
            [
                'ref' => CauseOfDeath::FleshCage,
                'label' => 'Im Fleischkäfig geendet',
                'icon' => 'caged',
                'desc' => 'Ein paar Leute in der Stadt mochten dich ganz offensichtlich nicht, darunter befanden sich mehrere Mitbürger, deine Nachbarn - ja sogar ein paar von deinen Freunden! Aus diesem Grund haben sie dich zur Stadtmauer geschleppt, dich in einen Käfig gesteckt und diesen über den Rand gestoßen. Das ganze ging ruck zuck. Jetzt bist du wohl Zombiefutter... Nach einem kurzen Applaus löste sich die Gruppe auf und jeder kehrte zu seiner Arbeit zurück.',
            ],
            [
                'ref' => CauseOfDeath::Strangulation,
                'label' => 'Strangulation',
                'icon' => 'strangulation',
                'desc' => 'Grüße von Brainbox aus der Vergangenheit, der am 22.02.2020 vor seinem PC sitzt und diesen Text schreibt.',
            ],
            [
                'ref' => CauseOfDeath::Headshot,
                'label' => 'Kopfschuss',
                'icon' => 'headshot',
                'desc' => 'Das hast du wohl nicht erwartet... Wie wäre es, wenn du dich das nächste mal an die Regeln hältst?',
            ],
            [
                'ref' => CauseOfDeath::Radiations,
                'label' => 'Tod durch Radioaktivität',
                'icon' => 'reactor',
                'desc' => 'Du warst gerade dabei dich daheim einzubunkern, als dir plötzlich auffällt dass etwas mit dem Reaktor nicht zu stimmen scheint, auf den die anderen Bürger so stolz sind... jetzt wurden sie durch eine ohrenbetäubende Explosion ausgelöscht. Als du wieder zu dir kommst liegst du in einem riesigen Krater, der einmal deine Stadt war; dein Blut kocht und deine Haut schmilzt dir vom Fleisch. Erst nach einigen Minuten unerträglicher Schmerzen verlässt auch du diese Welt.',
                'pictos' => ['r_dnucl_#00', 'r_dinfec_#00']
            ],
            [
                'ref' => CauseOfDeath::Apocalypse,
                'label' => 'Tod durch die Apokalypse',
                'icon' => 'apocalypse',
                'desc' => 'Eine Katastrophe apokalyptischen Ausmaßes hat dich, deine Stadt und alles in der Umgebung hinweggefegt. Es ist, als hätte jemand die gesamte Welt... gereinigt.',
            ],
            [
                'ref' => CauseOfDeath::Haunted,
                'label' => 'Besessen von einer gequälten Seele',
                'icon' => 'haunted',
                'desc' => 'Während du die Worte des Schamanen vernommen hast, dachtest du nie, dass er es ernst meinte. Nichts ist 100%ig sicher in dieser Wüste und der Trank, den er dir gegeben hat... Nun, er hat nicht funktioniert. Die gequälte Seele, der du zu helfen versuchtest, hat sich an dich geklammert und deine ungeschützte Seele mit Leichtigkeit überwältigt. Ihre Gewalt und Wut hinterließen nichts von dir als eine leere Hülle, dazu verdammt, von der Horde verschlungen zu werden.',
            ],

            [
                'ref' => CauseOfDeath::ExplosiveDoormat,
                'label' => 'Explosion',
                'icon' => 'exploded',
                'desc' => '???',
            ],
            [
                'ref' => CauseOfDeath::ChocolateCross,
                'label' => 'Schokoladenkreuz',
                'icon' => 'crucifixion',
                'desc' => '???',
                'pictos' => ['r_paques_#00']
            ],
            ,
            [
                'ref' => CauseOfDeath::LiverEaten,
                'label' => 'Verschlungene Leber',
                'icon' => 'eaten',
                'desc' => '???'
            ],
        ]);
    }
}