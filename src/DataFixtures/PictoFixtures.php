<?php

namespace App\DataFixtures;

use App\Entity\PictoPrototype;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;

class PictoFixtures extends Fixture
{
    public static $pictos = [
        [
            'label' => 'Heldentaten',
            'description' => 'Anzahl deiner wirklich außergewöhnlichen Heldentaten.',
            'icon' => 'r_heroac',
            'rare' => true
        ],
        [
            'label' => 'Alkohol',
            'description' => 'Anzahl der Liter selbstgebrannten Alkohols, den du gesoffen hast.',
            'icon' => 'r_alcool',
            'rare' => false
        ],
        [
            'label' => 'Hausverbesserung',
            'description' => 'Anzahl der Verbesserungen und Ausbauten, die du an deinem eigenem Haus vorgenommen hast.',
            'icon' => 'r_homeup',
            'rare' => false
        ],
        [
            'label' => 'Handwerk',
            'description' => 'Anzahl der Gegenstände, die du in der Werkstatt zusammengebaut oder zerstört hast.',
            'icon' => 'r_refine',
            'rare' => false
        ],
        [
            'label' => 'Leichenwäscher',
            'description' => 'Anzahl der Leichenwaschzeremonien, die du mit den Körpern deiner verstorbenen Mitbürger durchgeführt hast.',
            'icon' => 'r_cwater',
            'rare' => false
        ],
        [
            'label' => 'Autarker Verbannter',
            'description' => 'Anzahl der brauchbaren Gegenstände, die du in der Mülldeponie vor der Stadt gefunden hast. Nur Verbannte können diese Auszeichnung erhalten.',
            'icon' => 'r_solban',
            'rare' => false
        ],
        [
            'label' => 'Verbannungen',
            'description' => 'Anzahl der Stadtverbannungen.',
            'icon' => 'r_ban',
            'rare' => false
        ],
        [
            'label' => 'Schwere Verletzungen',
            'description' => 'Anzahl der Verstümmelungen und Gliedabtrennungen, die dich das Leben gekostet haben.',
            'icon' => 'r_wound',
            'rare' => false
        ],
        [
            'label' => 'Hausmannskost',
            'description' => 'Anzahl köstlicher Speisen, die du in der Küche gezaubert hast.',
            'icon' => 'r_cookr',
            'rare' => false
        ],
        [
            'label' => 'Metzger',
            'description' => 'Anzahl der Tiere, die du umgebracht hast (mit deinen Händen, im Mixer, in der Fäkalienhebeanlage...).',
            'icon' => 'r_animal',
            'rare' => false
        ],
        [
            'label' => 'Camper im Jenseits',
            'description' => 'Anzahl der Nächte, die du draußen überlebt hast als die Stadt schon zerstört war!',
            'icon' => 'r_cmplst',
            'rare' => true
        ],
        [
            'label' => 'Mutiger Camper',
            'description' => 'Anzahl der Nächte, die du draußen geschlafen und überlebt hast.',
            'icon' => 'r_camp',
            'rare' => false
        ],
        [
            'label' => 'Kannibalismus',
            'description' => 'Die Anzahl der Kilos an Menschenfleisch, die von dir verzehrt wurden...',
            'icon' => 'r_cannib',
            'rare' => false
        ],
        [
            'label' => 'Wasserkanonen',
            'description' => 'Anzahl an großen Wasserwaffen, die du gebaut hast.',
            'icon' => 'r_watgun',
            'rare' => true
        ],
        [
            'label' => 'Glückspilz',
            'description' => 'Anzahl der großen Metallkoffer, die du in der Außenwelt ausgegraben hast.',
            'icon' => 'r_chstxl',
            'rare' => true
        ],
        [
            'label' => 'Baustellen',
            'description' => 'Anzahl der Aktionspunkte, die du für den Bau neuer Kontruktionen und Stadtgebäude verwendet hast.',
            'icon' => 'r_buildr',
            'rare' => false
        ],
        [
            'label' => 'Clean',
            'description' => 'Anzahl der Punkte, die du erworben hast, indem du in einer Stadt komplett clean geblieben bist.',
            'icon' => 'r_nodrug',
            'rare' => false
        ],
        [
            'label' => 'Kernsammler',
            'description' => 'Anzahl der im Ausland gesammelten Seelen verstorbener Bürger',
            'icon' => 'r_collec',
            'rare' => false
        ],
        [
            'label' => 'Kämpfe um Leben und Tod',
            'description' => 'Anzahl der Zombies, die du mit den bloßen Händen umgebracht hast. Wow!',
            'icon' => 'r_wrestl',
            'rare' => false
        ],
        [
            'label' => 'Wunderwerke',
            'description' => 'Anzahl der Wunderwerke, an deren Bau du mitgewirkt hast.',
            'icon' => 'r_ebuild',
            'rare' => true
        ],
        [
            'label' => 'Grillmeister',
            'description' => 'Anzahl deiner Alten Freunde, die du im Kremato-Cue gegrillt hast.',
            'icon' => 'r_cooked',
            'rare' => false
        ],
        [
            'label' => 'Ausgrabungsarbeiten',
            'description' => 'Anzahl der Aktionspunkte, die du verbraucht hast, um eine Gebäuderuine in der Außenwelt freizulegen.',
            'icon' => 'r_digger',
            'rare' => false
        ],
        [
            'label' => 'Hauseinrichtung',
            'description' => 'Anzahl der Punkte, die du durch sinnlose Einrichtungsverschönerungen gewonnen hast.',
            'icon' => 'r_deco',
            'rare' => false
        ],
        [
            'label' => 'Letzte Verteidigungslinie',
            'description' => 'Du gehörst zu den letzten Bürgern der Stadt, die die Zombiehorde \'empfangen\' hat...',
            'icon' => 'r_surgrp',
            'rare' => true
        ],
        [
            'label' => 'Drogen',
            'description' => 'Anzahl kleiner lustiger Pillen, die du geschluckt oder sonstwie eingenommen hast.',
            'icon' => 'r_drug',
            'rare' => false
        ],
        [
            'label' => 'Drogenerfahrungen',
            'description' => 'Anzahl der gefährlichen Drogen, die du genommen hast.',
            'icon' => 'r_cobaye',
            'rare' => false
        ],
        [
            'label' => 'Gebäude erkunden',
            'description' => 'Anzahl der verlassenen Gebäude, die du untersucht hast.',
            'icon' => 'r_ruine',
            'rare' => false
        ],
        [
            'label' => 'Komplexe Expeditionen',
            'description' => 'Anzahl der entfernten Gebäude, die von dir erkundet wurden.',
            'icon' => 'r_explor',
            'rare' => false
        ],
        [
            'label' => 'Expertenexpeditionen',
            'description' => 'Anzahl SEHR WEIT entfernter Gebäude, die von dir erkundet wurden.',
            'icon' => 'r_explo2',
            'rare' => true
        ],
        [
            'label' => 'Großzügigkeit',
            'description' => 'Anzahl der Heldentage, die du einem anderem Bürger in Not geschenkt hast.',
            'icon' => 'r_share',
            'rare' => false
        ],
        [
            'label' => 'Spiritueller Führer',
            'description' => 'Anzahl der Tage, die du als Führer von Anfängern in den kleinen Regionen verbracht hast.',
            'icon' => 'r_guide',
            'rare' => false
        ],
        [
            'label' => 'Laborant',
            'description' => 'Anzahl der kleinen bunten Pillen aus deinem privaten Hobbylabor.',
            'icon' => 'r_drgmkr',
            'rare' => false
        ],
        [
            'label' => 'Diebstähle',
            'description' => 'Anzahl der Gegenstände, die du bei deinen (lebenden) Nachbarn geklaut hast.',
            'icon' => 'r_theft',
            'rare' => false
        ],
        [
            'label' => 'Schusseligkeiten',
            'description' => 'Anzahl der Gegenstände, die du durch seine Tollpatschigkeit kaputt gemacht hast.',
            'icon' => 'r_broken',
            'rare' => false
        ],
        [
            'label' => 'Masochismus',
            'description' => 'Anzahl der Glücksmomente, die du glücklich oder schmerzgeplagt genossen hast.',
            'icon' => 'r_maso',
            'rare' => true
        ],
        [
            'label' => 'Event-Auszeichnungen',
            'description' => 'Auszeichnung für die Teilnahme an Events bei \'Die Verdammten\'.',
            'icon' => 'r_bgum',
            'rare' => false
        ],
        [
            'label' => 'Wunderwerk: Sandschloss',
            'description' => 'Das hast du nicht wirklich gebaut?',
            'icon' => 'r_ebcstl',
            'rare' => true
        ],
        [
            'label' => 'Wunderwerk: Riesiger KVF',
            'description' => 'Huldigt den Riesigen KVF!',
            'icon' => 'r_ebpmv',
            'rare' => true
        ],
        [
            'label' => 'Wunderwerk: Rad des Schreckens',
            'description' => 'Sieh nur, man kann von da oben die Zombies sehen!',
            'icon' => 'r_ebgros',
            'rare' => true
        ],
        [
            'label' => 'Wunderwerk: Statue des Raben',
            'description' => 'Der Rabe ist gut, der Rabe ist ein Segen für die Stadt. [editiert vom Raben]',
            'icon' => 'r_ebcrow',
            'rare' => true
        ],
        [
            'label' => 'Nachrichten',
            'description' => 'Anzahl der sinnvollen (oder sinnlosen...) Nachrichten, die du im Stadtforum geschrieben hast.',
            'icon' => 'r_forum',
            'rare' => false
        ],
        [
            'label' => 'Dompteur',
            'description' => 'Anzahl der Tage, an denen du kleine Malteserhunde malträtiert hast.',
            'icon' => 'r_jtamer',
            'rare' => false
        ],
        [
            'label' => 'Aufklärer',
            'description' => 'Anzahl der Tage, die du als Aufklärer gespielt hast.',
            'icon' => 'r_jrangr',
            'rare' => false
        ],
        [
            'label' => 'Einsiedler',
            'description' => 'Anzahl der Tage, die du als Einsiedler gespielt hast.',
            'icon' => 'r_jermit',
            'rare' => false
        ],
        [
            'label' => 'Buddler',
            'description' => 'Anzahl der Tage, die du als Buddler gespielt hast.',
            'icon' => 'r_jcolle',
            'rare' => false
        ],
        [
            'label' => 'Wächter',
            'description' => 'Anzahl der Tage, die du als Wächter gespielt hast.',
            'icon' => 'r_jguard',
            'rare' => false
        ],
        [
            'label' => 'Techniker',
            'description' => 'Anzahl der Tage, die du als Techniker gespielt hast.',
            'icon' => 'r_jtech',
            'rare' => false
        ],
        [
            'label' => 'In deinem Bett gestorben',
            'description' => 'Großmutter, was hast du für große Zähne... Grrrooooaaar!',
            'icon' => 'r_dcity',
            'rare' => false
        ],
        [
            'label' => 'Dehydrationstode',
            'description' => 'Gibt die Anzahl der Dehydrationen an, an denen du gestorben bist. Doch, doch! Wasser ist wichtig !',
            'icon' => 'r_dwater',
            'rare' => false
        ],
        [
            'label' => 'Tödliche Infektionen',
            'description' => 'Anzahl der Infektionen, die dich das Leben gekostet haben.',
            'icon' => 'r_dinfec',
            'rare' => true
        ],
        [
            'label' => 'Tod durch Radioaktivität',
            'description' => 'Gibt an, wie oft du unter dem Einfluß von Radioaktivität das Zeitliche gesegnet hast.',
            'icon' => 'r_dnucl',
            'rare' => true
        ],
        [
            'label' => 'Tod durch Entzug',
            'description' => 'Die Anzahl der Male, in denen dein unfreiwilliger \'cold turkey\' gescheitert ist...',
            'icon' => 'r_ddrug',
            'rare' => false
        ],
        [
            'label' => 'Letzter Toter',
            'description' => 'Du bist die oder der letzte, die draufgeht. Diese Ehre wird nur sehr wenigen Bürgen zuteil.',
            'icon' => 'r_surlst',
            'rare' => true
        ],
        [
            'label' => 'Letzter Toter in einer Pandämoniumstadt',
            'description' => 'Du bist der oder die Letzte, die in einer Pandämoniumstadt draufgeht!',
            'icon' => 'r_suhard',
            'rare' => true
        ],
        [
            'label' => 'Mystic',
            'description' => 'Wie viele Seelen hat Ihre Stadt befreien können.',
            'icon' => 'r_mystic',
            'rare' => false
        ],
        [
            'label' => 'Wüstenausflüge',
            'description' => 'Die Auszeichnung spiegelt die Anzahl der Wüstenausflüge wieder, von denen du nicht mehr zurückgekehrt bist.',
            'icon' => 'r_doutsd',
            'rare' => false
        ],
        [
            'label' => 'Geöffnete Tür',
            'description' => 'Anzahl der verschlossenen Türen, die du in verlassenen Gebäuden geöffnet hast.',
            'icon' => 'r_door',
            'rare' => true
        ],
        [
            'label' => 'Hängend',
            'description' => 'Anzahl der Städte, in denen Sie nicht mehr unterstützt werden konnten.',
            'icon' => 'r_dhang',
            'rare' => false
        ],
        [
            'label' => 'Häuserplünderungen',
            'description' => 'Anzahl der Gegenstände, die du deinen noch verreckenden Freunden entrissen hast.',
            'icon' => 'r_plundr',
            'rare' => false
        ],
        [
            'label' => 'Absurde Projekte',
            'description' => 'Anzahl der Absurden Projekte, deren Fertigstellung du noch erlebt hast.',
            'icon' => 'r_wondrs',
            'rare' => true
        ],
        [
            'label' => 'Reparaturen',
            'description' => 'Anzahl kaputter Gegenstände, die du wieder repariert hast.',
            'icon' => 'r_repair',
            'rare' => false
        ],
        [
            'label' => 'Gebäudereparaturen',
            'description' => 'Anzahl der Aktionspunkte, die du in Reparaturen auf der Baustelle gesteckt hast.',
            'icon' => 'r_brep',
            'rare' => false
        ],
        [
            'label' => 'Textsammler',
            'description' => 'Anzahl der Dokumente und Texte, die du in der Wüste gefunden hast.',
            'icon' => 'r_rp',
            'rare' => true
        ],
        [
            'label' => 'Leichenentsorger',
            'description' => 'Anzahl deiner toten Freunde, die du außerhalb der Stadt entsorgt hast.',
            'icon' => 'r_cgarb',
            'rare' => false
        ],
        [
            'label' => 'Batteriewerferfabrikant',
            'description' => 'Anzahl der Batteriewerfer, die du gebaut hast.',
            'icon' => 'r_batgun',
            'rare' => true
        ],
        [
            'label' => 'Überlebende der Hölle!',
            'description' => 'Sie sind ein wahrer Überlebenskünstler der Hölle.',
            'icon' => 'r_pande',
            'rare' => true
        ],
        [
            'label' => 'Hausarbeiten',
            'description' => 'Anzahl der Ausbauten, die du an deinem Haus vorgenommen hast.',
            'icon' => 'r_hbuild',
            'rare' => false
        ],
        [
            'label' => 'Kettensägen',
            'description' => 'Anzahl an Kettensägen, die du gebaut hast.',
            'icon' => 'r_tronco',
            'rare' => true
        ],
        [
            'label' => 'Stadtwächter',
            'description' => 'Anzahl der Nächte, die du als Stadtwächter überlebt hast.',
            'icon' => 'r_guard',
            'rare' => false
        ],
        [
            'label' => 'Gerankte Stadt',
            'description' => 'Anzahl der Städte, in denen Du mitgespielt hast, die es in einer vergangenen Saison ins Ranking geschafft haben.',
            'icon' => 'r_winbas',
            'rare' => true
        ],
        [
            'label' => 'Legendäre Stadt',
            'description' => 'Anzahl der Städte, in denen Du mitgespielt hast, die in einer vergangenen Saison als Erste abgeschnitten haben.',
            'icon' => 'r_wintop',
            'rare' => true
        ],
        [
            'label' => 'Teilnehmende Stadt',
            'description' => 'Anzahl der Städte, die in der Top-35-Rangliste einer vergangenen Saison vertreten waren.',
            'icon' => 'r_winthi',
            'rare' => true
        ],
        [
            'label' => 'Getötete Zombies',
            'description' => 'Gibt die Gesamtanzahl, der von dir umgebrachten Zombies an. Jede Tötungsmethode wird gezählt.',
            'icon' => 'r_killz',
            'rare' => false
        ],
        [
            'label' => 'Ehemaliger Beta-Tester',
            'description' => 'Dies ist eine äußert seltene Auszeichnung, die den ersten Spielern von \'MyHordes\' vorbehalten ist.',
            'icon' => 'r_beta',
            'rare' => true
        ],
        [
            'label' => 'Sandbälle! Yeah!',
            'description' => 'Anzahl der Sandbälle, die du deinen Mitspielern ins Gesicht geworfen hast. Kicher, kicher...',
            'icon' => 'r_sandb',
            'rare' => true
        ],
        [
            'label' => 'Kreuzigung',
            'description' => 'Freuen Sie sich, Sie hätten nicht laufen müssen.',
            'icon' => 'r_paques',
            'rare' => true
        ],
        [
            'label' => 'Den Weihnachtsmann gibt es nicht',
            'description' => 'Anzahl der Geschenke, die du deinen Freunden geklaut hast.',
            'icon' => 'r_santac',
            'rare' => true
        ],
        [
            'label' => 'Schamane',
            'description' => 'Tagelang wurde an den Seelen der Toten herumgepfuscht.',
            'icon' => 'r_chaman',
            'rare' => true
        ],
        [
            'label' => 'Zeuge von Harmagedon',
            'description' => 'Eine äußerst seltene Auszeichnung, die den Seelen vorbehalten ist, die das große Harmagedon des Universums von Die Verdammten erlebt haben!',
            'icon' => 'r_armag',
            'rare' => true
        ],
        [
            'label' => 'Zeuge der großen Verseuchung',
            'description' => 'Äußerst seltene Marke, die für Seelen reserviert ist, die die Große Kontamination von Die Verdammten erlebt haben!',
            'icon' => 'r_ginfec',
            'rare' => true
        ],
        [
            'label' => 'Seelenpunkte',
            'description' => 'Anzahl der Seelenpunkte, die du durch deine Inkarnationen erhalten hast.',
            'icon' => 'r_ptame',
            'rare' => true
        ],
    ];

    private $entityManager;

    public function __construct(EntityManagerInterface $em)
    {
        $this->entityManager = $em;
    }

    protected function insert_pictos(ObjectManager $manager, ConsoleOutputInterface $out) {
        $out->writeln( '<comment>Pictos : ' . count(static::$pictos) . ' fixture entries available.</comment>' );

        // Set up console
        $progress = new ProgressBar( $out->section() );
        $progress->start( count(static::$pictos) );

        $cache = [];

        // Iterate over all entries
        foreach (static::$pictos as $name => $entry) {
            // Set up the icon cache
            if (!isset($cache[$entry['icon']])) $cache[$entry['icon']] = 0;
            else $cache[$entry['icon']]++;
            
            $entry_unique_id = $entry['icon'] . '_#' . str_pad($cache[$entry['icon']],2,'0',STR_PAD_LEFT);

            // Get existing entry, or create new one
            $entity = $this->entityManager->getRepository(PictoPrototype::class)->findOneByName($entry_unique_id);
            if ($entity === null) $entity = new PictoPrototype();

            // Set property
            $entity
                ->setName($entry_unique_id)
                ->setLabel($entry['label'])
                ->setDescription($entry['description'])
                ->setIcon($entry['icon'])
                ->setRare($entry['rare'])
            ;

            $manager->persist($entity);
            $progress->advance();
        }

        $manager->flush();
        $progress->finish();
    }

    public function load(ObjectManager $manager) {
        $output = new ConsoleOutput();
        $output->writeln('<info>Installing fixtures: Pictos content database</info>');
        $output->writeln("");

        $this->insert_pictos($manager, $output);
        $output->writeln("");
    }
}
