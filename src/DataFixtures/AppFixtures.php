<?php

namespace App\DataFixtures;

use App\Entity\AffectAP;
use App\Entity\AffectOriginalItem;
use App\Entity\AffectStatus;
use App\Entity\CitizenProfession;
use App\Entity\CitizenStatus;
use App\Entity\ItemAction;
use App\Entity\ItemCategory;
use App\Entity\ItemProperty;
use App\Entity\ItemPrototype;
use App\Entity\RequireItem;
use App\Entity\Requirement;
use App\Entity\RequireStatus;
use App\Entity\Result;
use App\Entity\TownClass;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;

class AppFixtures extends Fixture
{
    public static $item_category_data = [
        ["name"=>"root_rsc" ,"label"=>"Baustoffe"               ,"parent"=>null,"ordering"=>0],
            ["name"=>"rsc_b","label"=>"Grundlegend","parent"=>"root_rsc","ordering"=>0],
            ["name"=>"rsc"  ,"label"=>"Sonstiges"  ,"parent"=>"root_rsc","ordering"=>1],
        ["name"=>"root_food","label"=>"Grundnahrungsmittel"     ,"parent"=>null,"ordering"=>1],
            ["name"=>"food"  ,"label"=>"Nahrung und Wasser","parent"=>"root_food","ordering"=>0],
            ["name"=>"food_7","label"=>"Leckeres Essen"    ,"parent"=>"root_food","ordering"=>1],
            ["name"=>"food_a","label"=>"Alkohol"           ,"parent"=>"root_food","ordering"=>2],
            ["name"=>"food_g","label"=>"Menschenfleisch"   ,"parent"=>"root_food","ordering"=>3],
            ["name"=>"food_n","label"=>"Nicht Essbar"      ,"parent"=>"root_food","ordering"=>4],
        ["name"=>"root_drug","label"=>"Apotheke und Labor"      ,"parent"=>null,"ordering"=>2],
            ["name"=>"drug_d","label"=>"Drogen"                 ,"parent"=>"root_drug","ordering"=>0],
            ["name"=>"drug"  ,"label"=>"Chemikalien und Zubehör","parent"=>"root_drug","ordering"=>1],
        ["name"=>"root_exp" ,"label"=>"Expedition"              ,"parent"=>null,"ordering"=>3],
            ["name"=>"exp_b","label"=>"Taschen und Behälter","parent"=>"root_exp","ordering"=>0],
            ["name"=>"exp_s","label"=>"Nützliches"          ,"parent"=>"root_exp","ordering"=>1],
            ["name"=>"camp" ,"label"=>"Camping"             ,"parent"=>"root_exp","ordering"=>2],
        ["name"=>"root_aud" ,"label"=>"Angriff und Verteidigung","parent"=>null,"ordering"=>4],
            ["name"=>"armor" ,"label"=>"Verteidigung"       ,"parent"=>"root_aud","ordering"=>0],
            ["name"=>"weapon","label"=>"Waffen"             ,"parent"=>"root_aud","ordering"=>1],
            ["name"=>"aud_n" ,"label"=>"Nicht Einsatzbereit","parent"=>"root_aud","ordering"=>2],
        ["name"=>"root_misc","label"=>"Sonstiges"               ,"parent"=>null,"ordering"=>5],
            ["name"=>"box"      ,"label"=>"Kisten und Behälter","parent"=>"root_misc","ordering"=>0],
            ["name"=>"imp"      ,"label"=>"Wichtige Items"     ,"parent"=>"root_misc","ordering"=>1],
            ["name"=>"furniture","label"=>"Dekoration"         ,"parent"=>"root_misc","ordering"=>2],
            ["name"=>"misc"     ,"label"=>"Sonstige Items"     ,"parent"=>"root_misc","ordering"=>3],
    ];

    public static $item_prototype_data = [
        ["label"=>"Ration Wasser","icon"=>"water","category"=>"food","deco"=>0,"heavy"=>false],                                             // In Out
        ["label"=>"Batterie","icon"=>"pile","category"=>"misc","deco"=>0,"heavy"=>false],                                                   // In Out
        ["label"=>"Konservendose","icon"=>"can","category"=>"food_n","deco"=>0,"heavy"=>false],                                             // In Out
        ["label"=>"Offene Konservendose","icon"=>"can_open","category"=>"food","deco"=>0,"heavy"=>false],                                   // In Out
        ["label"=>"Batteriewerfer 1-PDTG (geladen)","icon"=>"pilegun","category"=>"weapon","deco"=>0,"heavy"=>false],                       // --
        ["label"=>"Taser","icon"=>"taser","category"=>"weapon","deco"=>0,"heavy"=>false],                                                   // --
        ["label"=>"Aqua-Splash (leer)","icon"=>"watergun_opt_empty","category"=>"aud_n","deco"=>0,"heavy"=>false],                          //
        ["label"=>"Handmixer (geladen)","icon"=>"mixergun","category"=>"weapon","deco"=>0,"heavy"=>false],                                  // --
        ["label"=>"Kettensäge (geladen)","icon"=>"chainsaw","category"=>"weapon","deco"=>0,"heavy"=>true],                                  // --
        ["label"=>"Rasenmäher","icon"=>"lawn","category"=>"weapon","deco"=>0,"heavy"=>true],                                                // --
        ["label"=>"Verstellbarer Schraubenschlüssel","icon"=>"wrench","category"=>"weapon","deco"=>0,"heavy"=>false],                       // -- ---
        ["label"=>"Schraubenzieher","icon"=>"screw","category"=>"weapon","deco"=>0,"heavy"=>false],                                         // --
        ["label"=>"Großer trockener Stock","icon"=>"staff","category"=>"weapon","deco"=>0,"heavy"=>false],                                  // --
        ["label"=>"Jagdmesser","icon"=>"knife","category"=>"weapon","deco"=>0,"heavy"=>false],                                              // --
        ["label"=>"Machete","icon"=>"cutcut","category"=>"weapon","deco"=>0,"heavy"=>false],                                                // --
        ["label"=>"Lächerliches Taschenmesser","icon"=>"small_knife","category"=>"weapon","deco"=>0,"heavy"=>false],                        // --
        ["label"=>"Schweizer Taschenmesser","icon"=>"swiss_knife","category"=>"weapon","deco"=>0,"heavy"=>false],                           // --
        ["label"=>"Teppichmesser","icon"=>"cutter","category"=>"weapon","deco"=>0,"heavy"=>false],                                          // --
        ["label"=>"Einkaufswagen","icon"=>"cart","category"=>"exp_b","deco"=>0,"heavy"=>false],                                             // --
        ["label"=>"Dosenöffner","icon"=>"can_opener","category"=>"weapon","deco"=>0,"heavy"=>false],                                        // --
        ["label"=>"Extra Tasche","icon"=>"bag","category"=>"exp_b","deco"=>0,"heavy"=>false],                                               // --
        ["label"=>"Streichholzschachtel","icon"=>"lights","category"=>"misc","deco"=>0,"heavy"=>false],                                     //
        ["label"=>"Beruhigungsspritze","icon"=>"xanax","category"=>"drug_d","deco"=>0,"heavy"=>false],                                      //
        ["label"=>"Schaukelstuhl","icon"=>"chair","category"=>"furniture","deco"=>5,"heavy"=>true],                                         // --
        ["label"=>"Staubiges Buch","icon"=>"rp_book","category"=>"imp","deco"=>0,"heavy"=>false],                                           //
        ["label"=>"Matratze","icon"=>"bed","category"=>"armor","deco"=>3,"heavy"=>true],                                                    // --
        ["label"=>"Ausgeschaltete Nachttischlampe","icon"=>"lamp","category"=>"furniture","deco"=>1,"heavy"=>false],                        //
        ["label"=>"Perser","icon"=>"carpet","category"=>"furniture","deco"=>10,"heavy"=>false],                                             // --
        ["label"=>"Mini Hi-Fi Anlage (defekt)","icon"=>"music_part","category"=>"furniture","deco"=>1,"heavy"=>true],                       //
        ["label"=>"Kette + Vorhängeschloss","icon"=>"lock","category"=>"furniture","deco"=>0,"heavy"=>false],                               // --
        ["label"=>"Fußabstreifer","icon"=>"door_carpet","category"=>"furniture","deco"=>5,"heavy"=>false],                                  // --
        ["label"=>"Ein paar Würfel","icon"=>"dice","category"=>"imp","deco"=>0,"heavy"=>false],                                             //
        ["label"=>"Motor","icon"=>"engine","category"=>"imp","deco"=>0,"heavy"=>true],                                                      // --
        ["label"=>"Riemen","icon"=>"courroie","category"=>"rsc","deco"=>0,"heavy"=>false],                                                  // --
        ["label"=>"Handvoll Schrauben und Muttern","icon"=>"meca_parts","category"=>"rsc_b","deco"=>0,"heavy"=>false],                      // --
        ["label"=>"Huhn","icon"=>"pet_chick","category"=>"food_n","deco"=>0,"heavy"=>false],                                                // --
        ["label"=>"Übelriechendes Schwein","icon"=>"pet_pig","category"=>"food_n","deco"=>0,"heavy"=>true],                                 // --
        ["label"=>"Riesige Ratte","icon"=>"pet_rat","category"=>"food_n","deco"=>0,"heavy"=>false],                                         // --
        ["label"=>"Bissiger Hund","icon"=>"pet_dog","category"=>"armor","deco"=>0,"heavy"=>false],                                          // --
        ["label"=>"Großer knuddeliger Kater","icon"=>"pet_cat","category"=>"weapon","deco"=>5,"heavy"=>false],                              // --
        ["label"=>"Zwei-Meter Schlange","icon"=>"pet_snake","category"=>"food_n","deco"=>0,"heavy"=>true],                                  // --
        ["label"=>"Vibrator (geladen)","icon"=>"vibr","category"=>"imp","deco"=>0,"heavy"=>false],                                          //
        ["label"=>"Anaboles Steroid","icon"=>"drug","category"=>"drug_d","deco"=>0,"heavy"=>false],                                         //
        ["label"=>"Leckeres Steak","icon"=>"meat","category"=>"food_7","deco"=>0,"heavy"=>false],                                           // In Out
        ["label"=>"Undefinierbares Fleisch","icon"=>"undef","category"=>"food","deco"=>0,"heavy"=>false],                                   // In Out
        ["label"=>"Zeltplane","icon"=>"sheet","category"=>"camp","deco"=>0,"heavy"=>false],                                                 // --
        ["label"=>"Superpraktischer Rucksack","icon"=>"bagxl","category"=>"exp_b","deco"=>0,"heavy"=>false],                                // --
        ["label"=>"Kanister","icon"=>"jerrycan","category"=>"food_n","deco"=>0,"heavy"=>false],                                             //
        ["label"=>"Krummes Holzbrett","icon"=>"wood2","category"=>"rsc_b","deco"=>0,"heavy"=>false],                                        // --
        ["label"=>"Alteisen","icon"=>"metal","category"=>"rsc_b","deco"=>0,"heavy"=>false],                                                 // --
        ["label"=>"Wasserbombe","icon"=>"grenade","category"=>"weapon","deco"=>0,"heavy"=>false],                                           // --
        ["label"=>"Blechplatte","icon"=>"plate","category"=>"armor","deco"=>0,"heavy"=>true],                                               // --
        ["label"=>"Kanisterpumpe (zerlegt)","icon"=>"jerrygun_part","category"=>"aud_n","deco"=>0,"heavy"=>false],                          //
        ["label"=>"Bandage","icon"=>"bandage","category"=>"drug","deco"=>0,"heavy"=>false],                                                 //
        ["label"=>"Grüne Bierflasche","icon"=>"vodka_de","category"=>"food_a","deco"=>0,"heavy"=>false],                                    //
        ["label"=>"Kanisterpumpe (leer)","icon"=>"jerrygun_off","category"=>"aud_n","deco"=>0,"heavy"=>false],                              //
        ["label"=>"Videoprojektor","icon"=>"cinema","category"=>"furniture","deco"=>0,"heavy"=>true],                                       // --
        ["label"=>"Sprengstoff","icon"=>"explo","category"=>"rsc","deco"=>0,"heavy"=>false],                                                // --
        ["label"=>"Menschenfleisch","icon"=>"hmeat","category"=>"food_g","deco"=>0,"heavy"=>false],                                         //
        ["label"=>"Plastiktüte","icon"=>"grenade_empty","category"=>"aud_n","deco"=>0,"heavy"=>false],                                      //
        ["label"=>"Explodierende Wasserbombe","icon"=>"bgrenade","category"=>"weapon","deco"=>0,"heavy"=>false],                            // --
        ["label"=>"Plastiktüte mit Sprengstoff","icon"=>"bgrenade_empty","category"=>"aud_n","deco"=>0,"heavy"=>false],                     //
        ["label"=>"Unvollständige Kettensäge","icon"=>"chainsaw_part","category"=>"aud_n","deco"=>0,"heavy"=>true],                         //
        ["label"=>"Unvollständiger Handmixer","icon"=>"mixergun_part","category"=>"aud_n","deco"=>0,"heavy"=>false],                        //
        ["label"=>"Klebeband","icon"=>"rustine","category"=>"rsc","deco"=>0,"heavy"=>false],                                                //
        ["label"=>"Zerlegter Rasenmäher","icon"=>"lawn_part","category"=>"aud_n","deco"=>0,"heavy"=>false],                                 //
        ["label"=>"Kupferrohr","icon"=>"tube","category"=>"rsc","deco"=>0,"heavy"=>false],                                                  // --
        ["label"=>"Wackliger Einkaufswagen","icon"=>"cart_part","category"=>"imp","deco"=>0,"heavy"=>true],                                 // --
        ["label"=>"Gürtel mit Tasche","icon"=>"pocket_belt","category"=>"exp_b","deco"=>0,"heavy"=>false],                                  // --
        ["label"=>"Twinoid 500mg","icon"=>"drug_hero","category"=>"drug_d","deco"=>0,"heavy"=>false],                                       //
        ["label"=>"Metallkiste","icon"=>"chest","category"=>"box","deco"=>0,"heavy"=>true],                                                 //
        ["label"=>"Großer Metallkoffer","icon"=>"chest_xl","category"=>"box","deco"=>0,"heavy"=>true],                                      //
        ["label"=>"Werkzeugkiste","icon"=>"chest_tools","category"=>"box","deco"=>0,"heavy"=>true],                                         //
        ["label"=>"Nachttischlampe (an)","icon"=>"lamp_on","category"=>"furniture","deco"=>3,"heavy"=>false],                               // --
        ["label"=>"Mini Hi-Fi Anlage (an)","icon"=>"music","category"=>"furniture","deco"=>10,"heavy"=>true],                               // --
        ["label"=>"Pharmazeutische Substanz","icon"=>"pharma","category"=>"drug","deco"=>0,"heavy"=>false],                                 //
        ["label"=>"Unverarbeitete Blechplatten","icon"=>"plate_raw","category"=>"aud_n","deco"=>0,"heavy"=>true],                           // --
        ["label"=>"'Wake The Dead'","icon"=>"rhum","category"=>"food_a","deco"=>0,"heavy"=>false],                                          //
        ["label"=>"Heißer Kaffee","icon"=>"coffee","category"=>"food","deco"=>0,"heavy"=>false],                                            //
        ["label"=>"Kaffeekocher","icon"=>"coffee_machine","category"=>"imp","deco"=>5,"heavy"=>true],                                       //
        ["label"=>"Unvollständiger Kaffeekocher","icon"=>"coffee_machine_part","category"=>"imp","deco"=>0,"heavy"=>true],                  //
        ["label"=>"Elektronisches Bauteil","icon"=>"electro","category"=>"rsc","deco"=>0,"heavy"=>false],                                   // --
        ["label"=>"Habseligkeiten eines Bürgers","icon"=>"chest_citizen","category"=>"box","deco"=>0,"heavy"=>true],                        //
        ["label"=>"Hydraton 100mg","icon"=>"drug_water","category"=>"drug_d","deco"=>0,"heavy"=>false],                                     //
        ["label"=>"Kassettenradio (ohne Strom)","icon"=>"radio_off","category"=>"furniture","deco"=>0,"heavy"=>false],                      //
        ["label"=>"Kassettenradio","icon"=>"radio_on","category"=>"furniture","deco"=>2,"heavy"=>false],                                    // --
        ["label"=>"Zyanid","icon"=>"cyanure","category"=>"drug","deco"=>0,"heavy"=>false],                                                  //
        ["label"=>"Alte Tür","icon"=>"door","category"=>"armor","deco"=>0,"heavy"=>true],                                                   // --
        ["label"=>"Verdächtiges Gemüse","icon"=>"vegetable","category"=>"food","deco"=>0,"heavy"=>false],                                   // In Out
        ["label"=>"Reparturset (kaputt)","icon"=>"repair_kit_part","category"=>"imp","deco"=>0,"heavy"=>false],                             // --
        ["label"=>"Reparaturset","icon"=>"repair_kit","category"=>"imp","deco"=>0,"heavy"=>false],                                          //
        ["label"=>"Wasserpistole (leer)","icon"=>"watergun_empty","category"=>"aud_n","deco"=>0,"heavy"=>false],                            //
        ["label"=>"Aqua-Splash (3 Ladungen)","icon"=>"watergun_opt_3","category"=>"weapon","deco"=>0,"heavy"=>false],                       // --
        ["label"=>"Aqua-Splash (2 Ladungen)","icon"=>"watergun_opt_2","category"=>"weapon","deco"=>0,"heavy"=>false],                       // --
        ["label"=>"Aqua-Splash (1 Ladung)","icon"=>"watergun_opt_1","category"=>"weapon","deco"=>0,"heavy"=>false],                         // --
        ["label"=>"Handmixer (ohne Strom)","icon"=>"mixergun_empty","category"=>"aud_n","deco"=>0,"heavy"=>false],                          //
        ["label"=>"Kettensäge (ohne Strom)","icon"=>"chainsaw_empty","category"=>"aud_n","deco"=>0,"heavy"=>true],                          //
        ["label"=>"Batteriewerfer 1-PDTG (entladen)","icon"=>"pilegun_empty","category"=>"aud_n","deco"=>0,"heavy"=>false],                 //
        ["label"=>"Taser (ohne Strom)","icon"=>"taser_empty","category"=>"aud_n","deco"=>0,"heavy"=>false],                                 //
        ["label"=>"Elektrischer Bauchmuskeltrainer (ohne Strom)","icon"=>"sport_elec_empty","category"=>"imp","deco"=>0,"heavy"=>false],    //
        ["label"=>"Elektrischer Bauchmuskeltrainer (geladen)","icon"=>"sport_elec","category"=>"imp","deco"=>0,"heavy"=>false],             //
        ["label"=>"Zerstörer (entladen)","icon"=>"big_pgun_empty","category"=>"aud_n","deco"=>0,"heavy"=>false],                            //
        ["label"=>"Zerstörer (geladen)","icon"=>"big_pgun","category"=>"weapon","deco"=>0,"heavy"=>false],                                  //
        ["label"=>"Unvollständiger Zerstörer","icon"=>"big_pgun_part","category"=>"aud_n","deco"=>0,"heavy"=>false],                        //
        ["label"=>"Zonenmarker 'Radius'","icon"=>"tagger","category"=>"exp_s","deco"=>0,"heavy"=>false],                                    // --
        ["label"=>"Leuchtrakete","icon"=>"flare","category"=>"misc","deco"=>0,"heavy"=>false],                                              // --
        ["label"=>"Kanisterpumpe (einsatzbereit)","icon"=>"jerrygun","category"=>"weapon","deco"=>0,"heavy"=>false],                        //
        ["label"=>"Ektorp-Gluten Stuhl","icon"=>"chair_basic","category"=>"furniture","deco"=>2,"heavy"=>true],                             // --
        ["label"=>"Revolver (entladen)","icon"=>"gun","category"=>"furniture","deco"=>5,"heavy"=>false],                                    // --
        ["label"=>"Sturmgewehr (entladen)","icon"=>"machine_gun","category"=>"furniture","deco"=>15,"heavy"=>false],                        // --
        ["label"=>"Zünder","icon"=>"deto","category"=>"rsc","deco"=>0,"heavy"=>false],                                                      // --
        ["label"=>"Zementsack","icon"=>"concrete","category"=>"imp","deco"=>0,"heavy"=>true],                                               //
        ["label"=>"Unförmige Zementblöcke","icon"=>"concrete_wall","category"=>"armor","deco"=>0,"heavy"=>true],                            // --
        ["label"=>"Etikettenloses Medikament","icon"=>"drug_random","category"=>"drug_d","deco"=>0,"heavy"=>false],                         //
        ["label"=>"Paracetoid 7g","icon"=>"disinfect","category"=>"drug_d","deco"=>0,"heavy"=>false],                                       //
        ["label"=>"Unkrautbekämpfungsmittel Ness-Quick","icon"=>"digger","category"=>"exp_s","deco"=>0,"heavy"=>false],                     // --
        ["label"=>"Nahrungsmittelkiste","icon"=>"chest_food","category"=>"box","deco"=>0,"heavy"=>true],                                    //
        ["label"=>"Doggybag","icon"=>"food_bag","category"=>"food","deco"=>0,"heavy"=>false],                                               //
        ["label"=>"Tüte mit labbrigen Chips","icon"=>"food_bar1","category"=>"food","deco"=>0,"heavy"=>false],                              // In Out
        ["label"=>"Verschimmelte Waffeln","icon"=>"food_bar2","category"=>"food","deco"=>0,"heavy"=>false],                                 // In Out
        ["label"=>"Trockene Kaugummis","icon"=>"food_bar3","category"=>"food","deco"=>0,"heavy"=>false],                                    // In Out
        ["label"=>"Ranzige Butterkekse","icon"=>"food_biscuit","category"=>"food","deco"=>0,"heavy"=>false],                                // In Out
        ["label"=>"Angebissene Hähnchenflügel","icon"=>"food_chick","category"=>"food","deco"=>0,"heavy"=>false],                           // In Out
        ["label"=>"Abgelaufene Pim's Kekse","icon"=>"food_pims","category"=>"food","deco"=>0,"heavy"=>false],                               // In Out
        ["label"=>"Fades Gebäck","icon"=>"food_tarte","category"=>"food","deco"=>0,"heavy"=>false],                                         // In Out
        ["label"=>"Verschimmelte Stulle","icon"=>"food_sandw","category"=>"food","deco"=>0,"heavy"=>false],                                 // In Out
        ["label"=>"Chinesische Nudeln","icon"=>"food_noodles","category"=>"food","deco"=>0,"heavy"=>false],                                 // In Out
        ["label"=>"Starke Gewürze","icon"=>"spices","category"=>"misc","deco"=>0,"heavy"=>false],                                           //
        ["label"=>"Gewürzte chinesische Nudeln","icon"=>"food_noodles_hot","category"=>"food_7","deco"=>0,"heavy"=>false],                  // In Out
        ["label"=>"Unvollständiges Kartenspiel","icon"=>"cards","category"=>"imp","deco"=>0,"heavy"=>false],                                //
        ["label"=>"Gesellschaftsspiel","icon"=>"game_box","category"=>"imp","deco"=>0,"heavy"=>false],                                      //
        ["label"=>"Aqua-Splash (zerlegt)","icon"=>"watergun_opt_part","category"=>"aud_n","deco"=>0,"heavy"=>false],                        //
        ["label"=>"Vibrator (entladen)","icon"=>"vibr_empty","category"=>"imp","deco"=>0,"heavy"=>false],                                   //
        ["label"=>"Knochen mit Fleisch","icon"=>"bone_meat","category"=>"food_g","deco"=>0,"heavy"=>false],                                 //
        ["label"=>"Angeknackster menschlicher Knochen","icon"=>"bone","category"=>"weapon","deco"=>0,"heavy"=>false],                       // --
        ["label"=>"Zusammengeschusterter Holzbalken","icon"=>"wood_beam","category"=>"rsc_b","deco"=>0,"heavy"=>true],                      // --
        ["label"=>"Metallstruktur","icon"=>"metal_beam","category"=>"rsc_b","deco"=>0,"heavy"=>true],                                       // --
        ["label"=>"Metalltrümmer","icon"=>"metal_bad","category"=>"rsc_b","deco"=>0,"heavy"=>false],                                        // --
        ["label"=>"Verrotteter Baumstumpf","icon"=>"wood_bad","category"=>"rsc_b","deco"=>0,"heavy"=>false],                                // --
        ["label"=>"Metallsäge","icon"=>"saw_tool","category"=>"imp","deco"=>0,"heavy"=>false],                                              // --
        ["label"=>"Gut erhaltener Holzscheit","icon"=>"wood_log","category"=>"rsc_b","deco"=>2,"heavy"=>true],                              // --
        ["label"=>"Defektes Elektrogerät","icon"=>"electro_box","category"=>"misc","deco"=>0,"heavy"=>false],                               // --
        ["label"=>"Möbelpackung","icon"=>"deco_box","category"=>"box","deco"=>0,"heavy"=>true],                                             //
        ["label"=>"Beschädigte Metallsäge","icon"=>"saw_tool_part","category"=>"imp","deco"=>0,"heavy"=>false],                             //
        ["label"=>"Getriebe","icon"=>"mecanism","category"=>"misc","deco"=>0,"heavy"=>false],                                               // --
        ["label"=>"Holzbock","icon"=>"trestle","category"=>"armor","deco"=>1,"heavy"=>true],                                                // --
        ["label"=>"Järpen-Tisch","icon"=>"table","category"=>"armor","deco"=>3,"heavy"=>true],                                              // --
        ["label"=>"Micropur Brausetablette","icon"=>"water_cleaner","category"=>"drug","deco"=>0,"heavy"=>false],                           //
        ["label"=>"Darmmelone","icon"=>"vegetable_tasty","category"=>"food_7","deco"=>0,"heavy"=>false],                                    // In Out
        ["label"=>"Raketenpulver","icon"=>"powder","category"=>"rsc","deco"=>0,"heavy"=>false],                                             // --
        ["label"=>"Schießpulverbombe","icon"=>"flash","category"=>"exp_s","deco"=>0,"heavy"=>false],                                        // --
        ["label"=>"Teddybär","icon"=>"teddy","category"=>"furniture","deco"=>0,"heavy"=>false],                                             //
        ["label"=>"Holzkistendeckel","icon"=>"wood_plate_part","category"=>"aud_n","deco"=>0,"heavy"=>true],                                // --
        ["label"=>"Solide Holzplatte","icon"=>"wood_plate","category"=>"armor","deco"=>0,"heavy"=>true],                                    // --
        ["label"=>"Geldbündel","icon"=>"money","category"=>"furniture","deco"=>7,"heavy"=>false],                                           // --
        ["label"=>"Loses Werkzeug","icon"=>"repair_kit_part_raw","category"=>"misc","deco"=>0,"heavy"=>false],                              // --
        ["label"=>"Radius Mark II (entladen)","icon"=>"radius_mk2_part","category"=>"exp_s","deco"=>0,"heavy"=>false],                      //
        ["label"=>"Radius Mark II","icon"=>"radius_mk2","category"=>"exp_s","deco"=>0,"heavy"=>false],                                      // --
        ["label"=>"Reparatur Fix","icon"=>"repair_one","category"=>"imp","deco"=>0,"heavy"=>false],                                         //
        ["label"=>"Unvollständiger Motor","icon"=>"engine_part","category"=>"imp","deco"=>0,"heavy"=>true],                                 // --
        ["label"=>"Alte Waschmaschine","icon"=>"machine_1","category"=>"imp","deco"=>2,"heavy"=>true],                                      // --
        ["label"=>"Krebserregender Ofen","icon"=>"machine_2","category"=>"imp","deco"=>2,"heavy"=>true],                                    // --
        ["label"=>"Minibar","icon"=>"machine_3","category"=>"imp","deco"=>2,"heavy"=>true],                                                 // --
        ["label"=>"Ein Brief ohne Adresse","icon"=>"rp_letter","category"=>"misc","deco"=>0,"heavy"=>false],                                //
        ["label"=>"Aufgewelltes Blatt","icon"=>"rp_scroll","category"=>"imp","deco"=>0,"heavy"=>false],                                     //
        ["label"=>"Betriebsanleitung","icon"=>"rp_manual","category"=>"misc","deco"=>0,"heavy"=>false],                                     //
        ["label"=>"Unleserliches Notizbuch","icon"=>"rp_book2","category"=>"imp","deco"=>0,"heavy"=>false],                                 //
        ["label"=>"Fotoalbum","icon"=>"rp_book","category"=>"misc","deco"=>0,"heavy"=>false],                                               //
        ["label"=>"Blätterstapel","icon"=>"rp_sheets","category"=>"imp","deco"=>0,"heavy"=>false],                                          //
        ["label"=>"Große rostige Kette","icon"=>"chain","category"=>"imp","deco"=>0,"heavy"=>false],                                        // --
        ["label"=>"Verdächtige Speise","icon"=>"dish","category"=>"food","deco"=>0,"heavy"=>false],                                         // In Out
        ["label"=>"Leckere Speise","icon"=>"dish_tasty","category"=>"food_7","deco"=>0,"heavy"=>false],                                     // In Out
        ["label"=>"Schrankkoffer","icon"=>"home_box_xl","category"=>"furniture","deco"=>0,"heavy"=>true],                                   // --
        ["label"=>"Kartons","icon"=>"home_box","category"=>"furniture","deco"=>0,"heavy"=>false],                                           // --
        ["label"=>"Nagelbare Barrikade","icon"=>"home_def","category"=>"furniture","deco"=>0,"heavy"=>true],                                // --
        ["label"=>"Ein Briefumschlag","icon"=>"book_gen_letter","category"=>"imp","deco"=>0,"heavy"=>false],                                //
        ["label"=>"Ein Paket","icon"=>"book_gen_box","category"=>"box","deco"=>0,"heavy"=>false],                                           //
        ["label"=>"Maschendrahtzaunstück","icon"=>"fence","category"=>"rsc","deco"=>0,"heavy"=>false],                                      // --
        ["label"=>"Wasserpistole (3 Ladungen)","icon"=>"watergun_3","category"=>"weapon","deco"=>0,"heavy"=>false],                         // --
        ["label"=>"Wasserpistole (2 Ladungen)","icon"=>"watergun_2","category"=>"weapon","deco"=>0,"heavy"=>false],                         // --
        ["label"=>"Wasserpistole (1 Ladung)","icon"=>"watergun_1","category"=>"weapon","deco"=>0,"heavy"=>false],                           // --
        ["label"=>"Aqua-Splash (5 Ladungen)","icon"=>"watergun_opt_5","category"=>"weapon","deco"=>0,"heavy"=>false],                       // --
        ["label"=>"Aqua-Splash (4 Ladungen)","icon"=>"watergun_opt_4","category"=>"weapon","deco"=>0,"heavy"=>false],                       // --
        ["label"=>"Angefangene Zigarettenschachtel","icon"=>"cigs","category"=>"misc","deco"=>0,"heavy"=>false],                            //
        ["label"=>"Druckregler PDTT Mark II","icon"=>"pilegun_upkit","category"=>"misc","deco"=>0,"heavy"=>false],                          // --
        ["label"=>"Batteriewerfer Mark II (leer)","icon"=>"pilegun_up_empty","category"=>"aud_n","deco"=>0,"heavy"=>false],                 //
        ["label"=>"Batteriewerfer Mark II (geladen)","icon"=>"pilegun_up","category"=>"weapon","deco"=>0,"heavy"=>false],                   // --
        ["label"=>"Zerquetschte Batterie","icon"=>"pile_broken","category"=>"misc","deco"=>0,"heavy"=>false],                               // --
        ["label"=>"Kiste mit Materialien (3)","icon"=>"rsc_pack_3","category"=>"box","deco"=>0,"heavy"=>true],                              //
        ["label"=>"Kiste mit Materialien (2)","icon"=>"rsc_pack_2","category"=>"box","deco"=>0,"heavy"=>true],                              //
        ["label"=>"Kiste mit Materialien (1)","icon"=>"rsc_pack_1","category"=>"box","deco"=>0,"heavy"=>true],                              //
        ["label"=>"Autotür","icon"=>"car_door","category"=>"armor","deco"=>0,"heavy"=>true],                                                // --
        ["label"=>"Beschädigte Autotür","icon"=>"car_door_part","category"=>"aud_n","deco"=>0,"heavy"=>true],                               // --
        ["label"=>"Giftfläschchen","icon"=>"poison","category"=>"imp","deco"=>0,"heavy"=>false],                                            //
        ["label"=>"Ätzmittel","icon"=>"poison_part","category"=>"imp","deco"=>0,"heavy"=>false],                                            // --
        ["label"=>"Ration Wasser (vergiftet)","icon"=>"water","category"=>"food","deco"=>0,"heavy"=>false],                                 // --
        ["label"=>"Anaboles Steroid (vergiftet)","icon"=>"drug","category"=>"drug_d","deco"=>0,"heavy"=>false],                             // --
        ["label"=>"Offene Konservendose (vergiftet)","icon"=>"can_open","category"=>"food","deco"=>0,"heavy"=>false],                       // --
        ["label"=>"Vorräte eines umsichtigen Bürgers","icon"=>"chest_hero","category"=>"box","deco"=>0,"heavy"=>true],                      //
        ["label"=>"Postpaket","icon"=>"postal_box","category"=>"box","deco"=>0,"heavy"=>false],                                             //
        ["label"=>"Lunch-Box","icon"=>"food_armag","category"=>"food_7","deco"=>0,"heavy"=>false],                                          //
        ["label"=>"Eine Handvoll Bonbons","icon"=>"food_candies","category"=>"food_7","deco"=>0,"heavy"=>false],                            // In Out
        ["label"=>"Sperrholzstück","icon"=>"out_def","category"=>"camp","deco"=>0,"heavy"=>false],                                          // --
        ["label"=>"Fackel","icon"=>"torch","category"=>"armor","deco"=>0,"heavy"=>false],                                                   // --
        ["label"=>"Verbrauchte Fackel","icon"=>"torch_off","category"=>"weapon","deco"=>0,"heavy"=>false],                                  // --
        ["label"=>"Getrocknete Marshmallows","icon"=>"chama","category"=>"food_n","deco"=>0,"heavy"=>false],                                // --
        ["label"=>"Geröstete Marshmallows","icon"=>"chama_tasty","category"=>"food_7","deco"=>0,"heavy"=>false],                            // In Out
        ["label"=>"PC-Gehäuse","icon"=>"pc","category"=>"weapon","deco"=>3,"heavy"=>true],                                                  // --
        ["label"=>"Safe","icon"=>"safe","category"=>"box","deco"=>0,"heavy"=>true],                                                         //
        ["label"=>"Eine Enzyklopädie","icon"=>"rp_twin","category"=>"misc","deco"=>0,"heavy"=>false],                                       //
        ["label"=>"Wasserspender (leer)","icon"=>"water_can_empty","category"=>"exp_s","deco"=>0,"heavy"=>true],                            //
        ["label"=>"Wasserspender (1 Ration)","icon"=>"water_can_1","category"=>"food","deco"=>0,"heavy"=>true],                             // In Out
        ["label"=>"Wasserspender (2 Rationen)","icon"=>"water_can_2","category"=>"food","deco"=>0,"heavy"=>true],                           // In Out
        ["label"=>"Wasserspender (3 Rationen)","icon"=>"water_can_3","category"=>"food","deco"=>0,"heavy"=>true],                           // In Out
        ["label"=>"Abgelaufene Betapropin-Tablette 5mg","icon"=>"beta_drug_bad","category"=>"drug_d","deco"=>0,"heavy"=>false],             //
        ["label"=>"Aasbeeren","icon"=>"fruit_sub_part","category"=>"misc","deco"=>0,"heavy"=>false],                                        //
        ["label"=>"Schleimige Kugel","icon"=>"fruit_part","category"=>"misc","deco"=>0,"heavy"=>false],                                     //
        ["label"=>"Fleischfetzen","icon"=>"flesh_part","category"=>"misc","deco"=>0,"heavy"=>false],                                        // --
        ["label"=>"Makabre Bombe","icon"=>"flesh","category"=>"exp_s","deco"=>0,"heavy"=>false],                                            //
        ["label"=>"Dickflüssige Substanz","icon"=>"pharma_part","category"=>"drug","deco"=>0,"heavy"=>false],                               //
        ["label"=>"Aasbeerenbrei","icon"=>"fruit","category"=>"food","deco"=>0,"heavy"=>false],                                             // In Out
        ["label"=>"Aasbeerenbrei (vergiftet)","icon"=>"fruit","category"=>"food","deco"=>0,"heavy"=>false],                                 // --
        ["label"=>"Verdächtiges Gemüse (vergiftet)","icon"=>"vegetable","category"=>"food","deco"=>0,"heavy"=>false],                       // --
        ["label"=>"Eisengefäß mit modrigem wasser","icon"=>"water_cup_part","category"=>"food_n","deco"=>0,"heavy"=>false],                 //
        ["label"=>"Gereinigtes modriges Wasser","icon"=>"water_cup","category"=>"food","deco"=>0,"heavy"=>false],                           // In Out
        ["label"=>"Notizzettel eines Verbannten","icon"=>"banned_note","category"=>"exp_s","deco"=>0,"heavy"=>false],                       //
        ["label"=>"Blutdurchtränkter Verband","icon"=>"infect_poison_part","category"=>"drug","deco"=>0,"heavy"=>false],                    //
        ["label"=>"Verfluchter Teddybär","icon"=>"teddy","category"=>"furniture","deco"=>0,"heavy"=>false],                                 //
        ["label"=>"Sägemehlsteak","icon"=>"woodsteak","category"=>"food_7","deco"=>0,"heavy"=>false],                                       // In Out
        ["label"=>"Abgetragene rote Jacke","icon"=>"christmas_suit_1","category"=>"misc","deco"=>0,"heavy"=>false],                         //
        ["label"=>"Zerrissene rote Hose","icon"=>"christmas_suit_2","category"=>"misc","deco"=>0,"heavy"=>false],                           //
        ["label"=>"Schweißtriefende rote Mütze","icon"=>"christmas_suit_3","category"=>"misc","deco"=>0,"heavy"=>false],                    //
        ["label"=>"Übelriechender Anzug aus einer anderen Zeit","icon"=>"christmas_suit_full","category"=>"misc","deco"=>0,"heavy"=>false], //
        ["label"=>"Mobiltelefon","icon"=>"iphone","category"=>"weapon","deco"=>0,"heavy"=>false],                                           // --
        ["label"=>"Ekliger Hautfetzen","icon"=>"smelly_meat","category"=>"camp","deco"=>0,"heavy"=>false],                                  // --
        ["label"=>"MagLite Kinderlampe (aus)","icon"=>"maglite_off","category"=>"furniture","deco"=>5,"heavy"=>false],                      //
        ["label"=>"MagLite Kinderlampe (1 Ladung)","icon"=>"maglite_1","category"=>"exp_s","deco"=>5,"heavy"=>false],                       // --
        ["label"=>"MagLite Kinderlampe (2 Ladungen)","icon"=>"maglite_2","category"=>"exp_s","deco"=>5,"heavy"=>false],                     // --
        ["label"=>"Leiche eines Reisenden","icon"=>"cadaver","category"=>"food_g","deco"=>0,"heavy"=>true],                                 //
        ["label"=>"Angenagte Leiche","icon"=>"cadaver_remains","category"=>"misc","deco"=>0,"heavy"=>true],                                 // -- ---
        ["label"=>"Rauchgranate 'Tannenduft'","icon"=>"smoke_bomb","category"=>"misc","deco"=>0,"heavy"=>false],                            // --
        ["label"=>"Sandball","icon"=>"sand_ball","category"=>"misc","deco"=>0,"heavy"=>false],                                              //
        ["label"=>"Normaler Bauplan (gewöhnlich)","icon"=>"bplan_c","category"=>"imp","deco"=>0,"heavy"=>false],                            //
        ["label"=>"Normaler Bauplan (ungewöhnlich)","icon"=>"bplan_u","category"=>"imp","deco"=>0,"heavy"=>false],                          //
        ["label"=>"Normaler Bauplan (selten)","icon"=>"bplan_r","category"=>"imp","deco"=>0,"heavy"=>false],                                //
        ["label"=>"Normaler Bauplan (sehr selten!)","icon"=>"bplan_e","category"=>"imp","deco"=>0,"heavy"=>false],                          //
        ["label"=>"Architektenkoffer","icon"=>"bplan_box","category"=>"box","deco"=>0,"heavy"=>true],                                       //
        ["label"=>"Versiegelter Architektenkoffer","icon"=>"bplan_box_e","category"=>"box","deco"=>0,"heavy"=>true],                        //
        ["label"=>"Ei","icon"=>"egg","category"=>"food_7","deco"=>0,"heavy"=>false],                                                        // In Out
        ["label"=>"Apfel","icon"=>"apple","category"=>"food_7","deco"=>0,"heavy"=>false],                                                   // In Out
        ["label"=>"Explosive Pampelmuse","icon"=>"boomfruit","category"=>"weapon","deco"=>0,"heavy"=>false],                                // --
        ["label"=>"Abgenutzte Kuriertasche","icon"=>"bplan_drop","category"=>"box","deco"=>0,"heavy"=>false],                               //
        ["label"=>"Magnet-Schlüssel","icon"=>"magneticKey","category"=>"exp_s","deco"=>0,"heavy"=>false],                                   //
        ["label"=>"Schlagschlüssel","icon"=>"bumpKey","category"=>"exp_s","deco"=>0,"heavy"=>false],                                        //
        ["label"=>"Dosenöffner (Schlüssel)","icon"=>"classicKey","category"=>"exp_s","deco"=>0,"heavy"=>false],                             //
        ["label"=>"Abdruck vom Magnet-Schlüssel","icon"=>"prints","category"=>"exp_s","deco"=>0,"heavy"=>false],                            // --
        ["label"=>"Abdruck vom Schlagschlüssel","icon"=>"prints","category"=>"exp_s","deco"=>0,"heavy"=>false],                             // --
        ["label"=>"Abdruck vom Dosenöffner","icon"=>"prints","category"=>"exp_s","deco"=>0,"heavy"=>false],                                 // --
        ["label"=>"Ghul-Serum","icon"=>"vagoul","category"=>"drug_d","deco"=>0,"heavy"=>false],                                             //
        ["label"=>"Hotel-Bauplan (ungewöhnlich)","icon"=>"bplan_u","category"=>"imp","deco"=>0,"heavy"=>false],                             //
        ["label"=>"Hotel-Bauplan (selten)","icon"=>"bplan_r","category"=>"imp","deco"=>0,"heavy"=>false],                                   //
        ["label"=>"Hotel-Bauplan (sehr selten!)","icon"=>"bplan_e","category"=>"imp","deco"=>0,"heavy"=>false],                             //
        ["label"=>"Bunker-Bauplan (ungewöhnlich)","icon"=>"bplan_u","category"=>"imp","deco"=>0,"heavy"=>false],                            //
        ["label"=>"Bunker-Bauplan (selten)","icon"=>"bplan_r","category"=>"imp","deco"=>0,"heavy"=>false],                                  //
        ["label"=>"Bunker-Bauplan (sehr selten!)","icon"=>"bplan_e","category"=>"imp","deco"=>0,"heavy"=>false],                            //
        ["label"=>"Hospital-Bauplan (ungewöhnlich)","icon"=>"bplan_u","category"=>"imp","deco"=>0,"heavy"=>false],                          //
        ["label"=>"Hospital-Bauplan (selten)","icon"=>"bplan_r","category"=>"imp","deco"=>0,"heavy"=>false],                                //
        ["label"=>"Hospital-Bauplan (sehr selten!)","icon"=>"bplan_e","category"=>"imp","deco"=>0,"heavy"=>false],                          //
        ["label"=>"Verirrte Seele","icon"=>"soul_blue","category"=>"imp","deco"=>0,"heavy"=>false],                                         //
        ["label"=>"Starke Seele","icon"=>"soul_red","category"=>"imp","deco"=>0,"heavy"=>false],                                            //
        ["label"=>"Schwache Seele","icon"=>"soul_blue","category"=>"imp","deco"=>0,"heavy"=>false],                                         //
        ["label"=>"Bierkrug","icon"=>"fest","category"=>"food_a","deco"=>0,"heavy"=>false],                                                 //
        ["label"=>"Brezel","icon"=>"bretz","category"=>"food","deco"=>0,"heavy"=>false],                                                    // In Out
        ["label"=>"Dackel","icon"=>"tekel","category"=>"weapon","deco"=>0,"heavy"=>false],                                                  //
        ["label"=>"Pfahlwerfer","icon"=>"rlaunc","category"=>"weapon","deco"=>0,"heavy"=>false],                                            //
        ["label"=>"Kalaschni-Splash","icon"=>"kalach","category"=>"weapon","deco"=>0,"heavy"=>false],                                       // --
        ["label"=>"Schnellgebauter Tisch","icon"=>"bureau","category"=>"furniture","deco"=>0,"heavy"=>true],                                // --
        ["label"=>"Leerer Automat","icon"=>"distri","category"=>"furniture","deco"=>0,"heavy"=>true],                                       // -- ---
        ["label"=>"Santas Rentier","icon"=>"renne","category"=>"misc","deco"=>0,"heavy"=>true],                                             // -- ---
        ["label"=>"Osterei","icon"=>"paques","category"=>"misc","deco"=>0,"heavy"=>false],                                                  // -- ---
        ["label"=>"ANZAC Badge","icon"=>"badge","category"=>"armor","deco"=>0,"heavy"=>false],                                              // -- ---
        ["label"=>"Kalashni-Splash (leer)","icon"=>"kalach","category"=>"weapon","deco"=>0,"heavy"=>false],                                 //
        ["label"=>"Drahtspule","icon"=>"wire","category"=>"rsc","deco"=>0,"heavy"=>false],                                                  // --
        ["label"=>"Ölkännchen","icon"=>"oilcan","category"=>"rsc","deco"=>0,"heavy"=>false],                                                // --
        ["label"=>"Konvexlinse","icon"=>"lens","category"=>"rsc","deco"=>0,"heavy"=>false],                                                 // --
        ["label"=>"Wütende Mieze (halb verdaut)","icon"=>"angryc","category"=>"weapon","deco"=>0,"heavy"=>false],                           // --
        ["label"=>"Tretmine","icon"=>"claymo","category"=>"weapon","deco"=>0,"heavy"=>false],                                               // --
        ["label"=>"Laserdiode","icon"=>"diode","category"=>"rsc","deco"=>0,"heavy"=>false],                                                 // --
        ["label"=>"Selbstgebaute Gitarre","icon"=>"guitar","category"=>"imp","deco"=>0,"heavy"=>false],                                     //
        ["label"=>"LSD","icon"=>"lsd","category"=>"drug","deco"=>0,"heavy"=>false],                                                         //
        ["label"=>"Starker Laserpointer (4 Schuss)","icon"=>"lpoint4","category"=>"weapon","deco"=>0,"heavy"=>false],                       // --
        ["label"=>"Starker Laserpointer (3 Schuss)","icon"=>"lpoint3","category"=>"weapon","deco"=>0,"heavy"=>false],                       // --
        ["label"=>"Starker Laserpointer (2 Schuss)","icon"=>"lpoint2","category"=>"weapon","deco"=>0,"heavy"=>false],                       // --
        ["label"=>"Starker Laserpointer (1 Schuss)","icon"=>"lpoint1","category"=>"weapon","deco"=>0,"heavy"=>false],                       // --
        ["label"=>"Starker Laserpointer (Leer)","icon"=>"lpoint","category"=>"weapon","deco"=>0,"heavy"=>false],                            //
        ["label"=>"Teleskop","icon"=>"scope","category"=>"imp","deco"=>0,"heavy"=>false],                                                   // -- ---
        ["label"=>"Unpersönliche Explodierende Fußmatte","icon"=>"trapma","category"=>"furniture","deco"=>0,"heavy"=>false],                //    ---
        ["label"=>"Chuck-Figur","icon"=>"chudol","category"=>"furniture","deco"=>15,"heavy"=>false],                                        // -- ---
        ["label"=>"Kleine Zen-Fibel","icon"=>"lilboo","category"=>"imp","deco"=>0,"heavy"=>false],                                          //
        ["label"=>"Trockene Kräuter","icon"=>"ryebag","category"=>"rsc","deco"=>0,"heavy"=>false],                                          // -- ---
        ["label"=>"Mutterkorn","icon"=>"fungus","category"=>"food","deco"=>0,"heavy"=>false],                                               // -- ---
        ["label"=>"Korn-Bräu","icon"=>"hmbrew","category"=>"food","deco"=>0,"heavy"=>false],                                                //
        ["label"=>"Verfluchte HiFi","icon"=>"hifiev","category"=>"furniture","deco"=>0,"heavy"=>true],                                      // -- ---
        ["label"=>"Phil Collins CD","icon"=>"cdphil","category"=>"furniture","deco"=>1,"heavy"=>false],                                     // -- ---
        ["label"=>"Ohrstöpsel","icon"=>"bquies","category"=>"rsc","deco"=>0,"heavy"=>false],                                                // -- ---
        ["label"=>"Kaputter Stock","icon"=>"staff","category"=>"rsc","deco"=>0,"heavy"=>false],                                             //
        ["label"=>"Britney Spears CD","icon"=>"cdbrit","category"=>"furniture","deco"=>3,"heavy"=>false],                                   // -- ---
        ["label"=>"Best of The King CD","icon"=>"cdelvi","category"=>"furniture","deco"=>7,"heavy"=>false],                                 // -- ---
        ["label"=>"Rock n Roll HiFi","icon"=>"dfhifi","category"=>"furniture","deco"=>0,"heavy"=>true],                                     // -- ---
        ["label"=>"Verteidigende HiFi","icon"=>"dfhifi","category"=>"furniture","deco"=>0,"heavy"=>true],                                   // -- ---
        ["label"=>"Schrödingers Box","icon"=>"catbox","category"=>"box","deco"=>0,"heavy"=>false],                                          //
        ["label"=>"Geistiger Beistand","icon"=>"chkspk","category"=>"misc","deco"=>0,"heavy"=>false],                                       // -- ---
        ["label"=>"Fette Python","icon"=>"pet_snake2","category"=>"misc","deco"=>0,"heavy"=>true],                                          // -- ---
        ["label"=>"Überraschungskiste (3 Geschenke)","icon"=>"chest_christmas_3","category"=>"misc","deco"=>0,"heavy"=>true],               //
        ["label"=>"Überraschungskiste (2 Geschenke)","icon"=>"chest_christmas_2","category"=>"misc","deco"=>0,"heavy"=>true],               //
        ["label"=>"Überraschungskiste (1 Geschenk)","icon"=>"chest_christmas_1","category"=>"misc","deco"=>0,"heavy"=>true],                //
    ];
    public static $item_prototype_properties = [
        'can_opener_#00'  => [ 'can_opener' ],
        'saw_tool_#00'    => [ 'can_opener' ],
        'screw_#00'       => [ 'can_opener' ],
        'swiss_knife_#00' => [ 'can_opener' ],
    ];

    public static $profession_data = [
        ['name'=>'none'    ,'label'=>'Gammler' ],
        ['name'=>'basic'   ,'label'=>'Einwohner' ],
        ['name'=>'collec'  ,'label'=>'Buddler' ],
        ['name'=>'guardian','label'=>'Wächter' ],
        ['name'=>'hunter'  ,'label'=>'Aufklärer' ],
        ['name'=>'tamer'   ,'label'=>'Dompteur' ],
        ['name'=>'tech'    ,'label'=>'Techniker' ],
        ['name'=>'shaman'  ,'label'=>'Schamane' ],
    ];

    public static $town_class_data = [
        ['name'=>'small'  ,'label'=>'Kleine Stadt' ],
        ['name'=>'remote' ,'label'=>'Entfernte Regionen' ],
        ['name'=>'panda'  ,'label'=>'Pandämonium' ],
        ['name'=>'custom' ,'label'=>'Private Stadt' ],
    ];

    public static $citizen_status = [
        ['name' => 'clean', 'label' => 'Clean'],
        ['name' => 'hasdrunk', 'label' => 'Getrunken'],
        ['name' => 'haseaten', 'label' => 'Satt'],
        ['name' => 'camper', 'label' => 'Umsichtiger Camper'],
        ['name' => 'immune', 'label' => 'Immunisiert'],
        ['name' => 'hsurvive', 'label' => 'Den Tod besiegen'],
        ['name' => 'tired', 'label' => 'Erschöpfung'],
        ['name' => 'terror', 'label' => 'Angststarre'],
        ['name' => 'thirst1', 'label' => 'Durst'],
        ['name' => 'thirst2', 'label' => 'Dehydriert'],
        ['name' => 'drugged', 'label' => 'Rauschzustand'],
        ['name' => 'addict', 'label' => 'Drogenabhängig'],
        ['name' => 'infection', 'label' => 'Infektion'],
        ['name' => 'drunk', 'label' => 'Trunkenheit'],
        ['name' => 'hungover', 'label' => 'Kater'],
        ['name' => 'wound1', 'label' => 'Verwundung - Kopf'],
        ['name' => 'wound2', 'label' => 'Verwundung - Hände'],
        ['name' => 'wound3', 'label' => 'Verwundung - Arme'],
        ['name' => 'wound4', 'label' => 'Verwundung - Bein'],
        ['name' => 'wound5', 'label' => 'Verwundung - Auge'],
        ['name' => 'wound6', 'label' => 'Verwundung - Fuß'],
        ['name' => 'ghul', 'label' => 'Ghul'],
        ['name' => 'healed', 'label' => 'Bandagiert'],
    ];

    public static $item_actions = [
        'meta_requirements' => [
            'drink_ap_1'  => [ 'type' => Requirement::CrossOnFail, 'collection' => [ 'status' => 'has_not_drunken' ]],
            'drink_ap_2'  => [ 'type' => Requirement::HideOnFail,  'collection' => [ 'status' => 'not_dehydrated' ]],
            'drink_no_ap' => [ 'type' => Requirement::HideOnFail,  'collection' => [ 'status' => 'dehydrated' ]],
            'eat_ap'      => [ 'type' => Requirement::CrossOnFail, 'collection' => [ 'status' => 'has_not_eaten' ]],

            'have_can_opener' => [ 'type' => Requirement::MessageOnFail, 'collection' => [ 'item' => 'have_can_opener' ],  'text' => 'Du brauchst ein Werkzeug, um diesen Gegenstand zu öffnen...' ]
        ],

        'requirements' => [
            'status' => [
                'has_not_drunken' => [ 'enabled' => false, 'status' => 'hasdrunk' ],
                'has_not_eaten'   => [ 'enabled' => false, 'status' => 'haseaten' ],
                'not_dehydrated'  => [ 'enabled' => false, 'status' => 'thirst2' ],
                'dehydrated'      => [ 'enabled' => true,  'status' => 'thirst2' ],
            ],
            'item' => [
                'have_can_opener' => [ 'item' => null, 'prop' => 'can_opener' ],
            ]
        ],

        'meta_results' => [
            'consume_item'=> [ 'collection' => [ 'item' => 'consume' ]],

            'drink_ap_1'  => [ 'collection' => [ 'status' => 'add_has_drunk', 'ap' => 'to_max_plus_0' ]],
            'drink_ap_2'  => [ 'collection' => [ 'status' => 'remove_thirst' ]],
            'drink_no_ap' => [ 'collection' => [ 'status' => 'replace_dehydration' ]],
            'eat_ap6'     => [ 'collection' => [ 'status' => 'add_has_eaten', 'ap' => 'to_max_plus_0' ]],
            'eat_ap7'     => [ 'collection' => [ 'status' => 'add_has_eaten', 'ap' => 'to_max_plus_1' ]],

            'produce_open_can' =>  [ 'collection' => [ 'item' => 'produce_open_can' ]],
            'produce_watercan2' => [ 'collection' => [ 'item' => 'produce_watercan2' ]],
            'produce_watercan1' => [ 'collection' => [ 'item' => 'produce_watercan1' ]],
            'produce_watercan0' => [ 'collection' => [ 'item' => 'produce_watercan0' ]],
        ],

        'results' => [
            'ap' => [
                'to_max_plus_0' => [ 'max' => true, 'num' => 0 ],
                'to_max_plus_1' => [ 'max' => true, 'num' => 1 ],
                'to_max_plus_2' => [ 'max' => true, 'num' => 2 ],
                'to_max_plus_3' => [ 'max' => true, 'num' => 3 ],
            ],
            'status' => [
                'replace_dehydration' => [ 'from' => 'thirst2', 'to' => 'thirst1' ],
                'add_has_drunk' => [ 'from' => null, 'to' => 'hasdrunk' ],
                'add_has_eaten' => [ 'from' => null, 'to' => 'haseaten' ],
                'remove_thirst' => [ 'from' => 'thirst1', 'to' => null ]
            ],
            'item' => [
                'consume' => [ 'consume' => true, 'morph' => null ],

                'produce_open_can' =>  [ 'consume' => false, 'morph' => 'can_open_#00' ],
                'produce_watercan2' => [ 'consume' => false, 'morph' => 'water_can_2_#00' ],
                'produce_watercan1' => [ 'consume' => false, 'morph' => 'water_can_1_#00' ],
                'produce_watercan0' => [ 'consume' => false, 'morph' => 'water_can_empty_#00' ],
            ]
        ],

        'actions' => [
            'water_6ap' => [ 'label' => 'Trinken', 'meta' => [ 'drink_ap_1', 'drink_ap_2' ], 'result' => [ 'drink_ap_1', 'drink_ap_2', 'consume_item' ] ],
            'water_0ap' => [ 'label' => 'Trinken', 'meta' => [ 'drink_no_ap' ], 'result' => [ 'drink_no_ap', 'consume_item' ] ],

            'watercan3_6ap' => [ 'label' => 'Trinken', 'meta' => [ 'drink_ap_1', 'drink_ap_2' ], 'result' => [ 'drink_ap_1', 'drink_ap_2', 'produce_watercan2' ] ],
            'watercan3_0ap' => [ 'label' => 'Trinken', 'meta' => [ 'drink_no_ap' ], 'result' => [ 'drink_no_ap', 'produce_watercan2' ] ],
            'watercan2_6ap' => [ 'label' => 'Trinken', 'meta' => [ 'drink_ap_1', 'drink_ap_2' ], 'result' => [ 'drink_ap_1', 'drink_ap_2', 'produce_watercan1' ] ],
            'watercan2_0ap' => [ 'label' => 'Trinken', 'meta' => [ 'drink_no_ap' ], 'result' => [ 'drink_no_ap', 'produce_watercan1' ] ],
            'watercan1_6ap' => [ 'label' => 'Trinken', 'meta' => [ 'drink_ap_1', 'drink_ap_2' ], 'result' => [ 'drink_ap_1', 'drink_ap_2', 'produce_watercan0' ] ],
            'watercan1_0ap' => [ 'label' => 'Trinken', 'meta' => [ 'drink_no_ap' ], 'result' => [ 'drink_no_ap', 'produce_watercan0' ] ],

            'can'       => [ 'label' => 'Öffnen',  'meta' => [ 'have_can_opener' ], 'result' => [ 'produce_open_can' ] ],

            'eat_6ap'   => [ 'label' => 'Essen',   'meta' => [ 'eat_ap' ], 'result' => [ 'eat_ap6', 'consume_item' ] ],
            'eat_7ap'   => [ 'label' => 'Essen',   'meta' => [ 'eat_ap' ], 'result' => [ 'eat_ap7', 'consume_item' ] ],
        ],
        'items' => [
            'water_#00'           => [ 'water_6ap', 'water_0ap' ],
            'water_cup_#00'       => [ 'water_6ap', 'water_0ap' ],
            'water_can_3_#00'     => [ 'watercan3_6ap', 'watercan3_0ap' ],
            'water_can_2_#00'     => [ 'watercan2_6ap', 'watercan2_0ap' ],
            'water_can_1_#00'     => [ 'watercan1_6ap', 'watercan1_0ap' ],
            'can_#00'             => [ 'can' ],
            'can_open_#00'        => [ 'eat_6ap'],
            'fruit_#00'           => [ 'eat_6ap'],
            'bretz_#00'           => [ 'eat_6ap'],
            'undef_#00'           => [ 'eat_6ap'],
            'dish_#00'            => [ 'eat_6ap'],
            'vegetable_#00'       => [ 'eat_6ap'],
            'food_bar1_#00'       => [ 'eat_6ap'],
            'food_bar2_#00'       => [ 'eat_6ap'],
            'food_bar3_#00'       => [ 'eat_6ap'],
            'food_biscuit_#00'    => [ 'eat_6ap'],
            'food_chick_#00'      => [ 'eat_6ap'],
            'food_pims_#00'       => [ 'eat_6ap'],
            'food_tarte_#00'      => [ 'eat_6ap'],
            'food_sandw_#00'      => [ 'eat_6ap'],
            'food_noodles_#00'    => [ 'eat_6ap'],
            'food_noodles_hot_#00'=> [ 'eat_7ap'],
            'meat_#00'            => [ 'eat_7ap'],
            'vegetable_tasty_#00' => [ 'eat_7ap'],
            'dish_tasty_#00'      => [ 'eat_7ap'],
            'food_candies_#00'    => [ 'eat_7ap'],
            'chama_tasty_#00'     => [ 'eat_7ap'],
            'woodsteak_#00'       => [ 'eat_7ap'],
            'egg_#00'             => [ 'eat_7ap'],
            'apple_#00'           => [ 'eat_7ap'],
        ]

    ];

    private $entityManager;

    public function __construct(EntityManagerInterface $em)
    {
        $this->entityManager = $em;
    }

    protected function insert_item_categories(ObjectManager $manager, ConsoleOutputInterface $out) {
        // Mark all entries as "not imported"
        $changed = true;
        $missing_data = static::$item_category_data;
        $out->writeln( '<comment>Item categories: ' . count(static::$item_category_data) . ' fixture entries available.</comment>' );

        // Set up console
        $table = new Table( $out );
        $table->setHeaders( ['ID','Name','Label','Parent','Ordering'] );
        $progress = new ProgressBar( $out->section() );
        $progress->start( count(static::$item_category_data) );

        // As long as the last query performed any changes and we still have not-imported data , continue
        while ($changed && !empty($missing_data)) {
            // Set the current data, and clear state
            $current = $missing_data;
            $missing_data = [];
            $changed = false;

            // For each missing entry
            foreach ($current as $entry) {

                // Check if this entry has a parent, and attempt to fetch the parent from the database
                $parent = null;
                if ($entry['parent'] !== null) {
                    $parent = $this->entityManager->getRepository(ItemCategory::class)->findOneByName( $entry['parent'] );
                    // If the entry has a parent, but that parent is missing from the database,
                    // defer the current entry for the next run
                    if ($parent === null) {
                        $missing_data[] = $entry;
                        continue;
                    }
                }

                // Attempt to fetch the current entry from the database; if the entry does not exist, create a new one
                $entity = $this->entityManager->getRepository(ItemCategory::class)->findOneByName( $entry['name'] );
                if (!$entity) $entity = new ItemCategory();

                // Set properties
                $entity->setName( $entry['name'] );
                $entity->setLabel( $entry['label'] );
                $entity->setOrdering( $entry['ordering'] );
                $entity->setParent( $entry['parent'] === null ? null :
                    $this->entityManager->getRepository(ItemCategory::class)->findOneByName( $entry['parent'] )
                );

                // Persist entry
                $manager->persist( $entity );
                $progress->advance();
                $table->addRow( [ $entity->getId(), $entity->getName(), $entity->getLabel(), $entity->getParent() ? $entity->getParent()->getName() : '', $entity->getOrdering() ] );
                $changed = true;
            }

            // Flush
            $manager->flush();
            $progress->finish();
            $table->render();
        }

        if (!empty($missing_data)) {
            $out->writeln('<error>Unable to insert all fixtures. The following entries are missing:</error>');
            $table2 = new Table( $out->section() );
            $table2->setHeaders( ['Name','Label','Parent','Ordering'] );
            foreach ($missing_data as $entry)
                $table2->addRow( [ $entry['name'], $entry['label'], $entry['parent'], $entry['ordering'] ] );
            $table2->render();
        }
    }

    protected function insert_item_prototypes(ObjectManager $manager, ConsoleOutputInterface $out) {

        $out->writeln( '<comment>Item prototypes: ' . count(static::$item_prototype_data) . ' fixture entries available.</comment>' );

        // Get misc category
        $misc_category = $this->entityManager->getRepository(ItemCategory::class)->findOneByName( 'misc' );
        $cache = [];

        // Set up console
        $table = new Table( $out );
        $table->setHeaders( ['ID','Name','Label','Icon','Category','Deco'] );
        $progress = new ProgressBar( $out->section() );
        $progress->start( count(static::$item_prototype_data) );

        $properties = [];

        // Iterate over all entries
        foreach (static::$item_prototype_data as $entry) {
            // Set up the icon cache
            if (!isset($cache[$entry['icon']])) $cache[$entry['icon']] = 0;
            else $cache[$entry['icon']]++;

            // Generate unique ID
            $entry_unique_id = $entry['icon'] . '_#' . str_pad($cache[$entry['icon']],2,'0',STR_PAD_LEFT);

            // Check the category
            $category = $this->entityManager->getRepository(ItemCategory::class)->findOneByName( $entry['category'] );
            if ($category === null) {
                $category = $misc_category;
                $out->writeln('<error>Unable to locate category \'' . $entry['category'] . '\' for item \'' .
                    $entry_unique_id . '\'. Assigning MISC category instead.</error>');
            }
            if ($category === null) {
                $out->writeln('<error>Unable to locate category \'' . $entry['category'] . '\' for item \'' .
                    $entry_unique_id . '\'. MISC category could not be located, entry will be skipped!</error>');
                continue;
            }

            // Get existing entry, or create new one
            $entity = $this->entityManager->getRepository(ItemPrototype::class)->findOneByName( $entry_unique_id );
            if ($entity === null) $entity = new ItemPrototype();

            // Set property
            $entity->setName( $entry_unique_id );
            $entity->setLabel( $entry['label'] );
            $entity->setIcon( $entry['icon'] );
            $entity->setDeco( $entry['deco'] );
            $entity->setHeavy( $entry['heavy'] );
            $entity->setCategory( $category );
            $entity->getProperties()->clear();

            if (isset(static::$item_prototype_properties[$entry_unique_id]))
                foreach (static::$item_prototype_properties[$entry_unique_id] as $property) {
                    if (!isset($properties[$property])) {
                        $properties[$property] = $manager->getRepository(ItemProperty::class)->findOneByName( $property );
                        if (!$properties[$property]) {
                            $p = new ItemProperty();
                            $p->setName( $property );
                            $properties[$property] = $p;
                            $manager->persist( $properties[$property] );
                        }
                    }
                    $entity->addProperty( $properties[$property] );
                }

            $manager->persist( $entity );

            // Set table entry
            $table->addRow( [$entity->getId(),$entity->getName(),$entity->getLabel(),$entity->getIcon(),$entity->getCategory()->getName(),$entity->getDeco()] );
            $progress->advance();
        }

        // Flush
        $manager->flush();
        $progress->finish();
        $table->render();
    }

    protected function insert_professions(ObjectManager $manager, ConsoleOutputInterface $out) {
        $out->writeln( '<comment>Citizen professions: ' . count(static::$profession_data) . ' fixture entries available.</comment>' );

        // Set up console
        $table = new Table( $out );
        $table->setHeaders( ['ID','Name','Label'] );
        $progress = new ProgressBar( $out->section() );
        $progress->start( count(static::$profession_data) );

        // Iterate over all entries
        foreach (static::$profession_data as $entry) {
            // Get existing entry, or create new one
            $entity = $this->entityManager->getRepository(CitizenProfession::class)->findOneByName( $entry['name'] );
            if ($entity === null) $entity = new CitizenProfession();

            // Set property
            $entity->setName( $entry['name'] );
            $entity->setLabel( $entry['label'] );

            $manager->persist( $entity );

            // Set table entry
            $table->addRow( [$entity->getId(),$entity->getName(),$entity->getLabel()] );
            $progress->advance();
        }

        $manager->flush();
        $progress->finish();
        $table->render();
    }

    protected function insert_town_classes(ObjectManager $manager, ConsoleOutputInterface $out) {
        $out->writeln( '<comment>Town classes: ' . count(static::$town_class_data) . ' fixture entries available.</comment>' );

        // Set up console
        $table = new Table( $out );
        $table->setHeaders( ['ID','Name','Label'] );
        $progress = new ProgressBar( $out->section() );
        $progress->start( count(static::$town_class_data) );

        // Iterate over all entries
        foreach (static::$town_class_data as $entry) {
            // Get existing entry, or create new one
            $entity = $this->entityManager->getRepository(TownClass::class)->findOneByName( $entry['name'] );
            if ($entity === null) $entity = new TownClass();

            // Set property
            $entity->setName( $entry['name'] );
            $entity->setLabel( $entry['label'] );

            $manager->persist( $entity );

            // Set table entry
            $table->addRow( [$entity->getId(),$entity->getName(),$entity->getLabel()] );
            $progress->advance();
        }

        $manager->flush();
        $progress->finish();
        $table->render();
    }

    protected function insert_status_types(ObjectManager $manager, ConsoleOutputInterface $out) {
        $out->writeln( '<comment>Status: ' . count(static::$citizen_status) . ' fixture entries available.</comment>' );

        // Set up console
        $table = new Table( $out );
        $table->setHeaders( ['ID','Name','Label'] );
        $progress = new ProgressBar( $out->section() );
        $progress->start( count(static::$citizen_status) );

        // Iterate over all entries
        foreach (static::$citizen_status as $entry) {
            // Get existing entry, or create new one
            $entity = $this->entityManager->getRepository(CitizenStatus::class)->findOneByName( $entry['name'] );
            if ($entity === null) $entity = new CitizenStatus();

            // Set property
            $entity->setName( $entry['name'] );
            $entity->setLabel( $entry['label'] );

            $manager->persist( $entity );

            // Set table entry
            $table->addRow( [$entity->getId(),$entity->getName(),$entity->getLabel()] );
            $progress->advance();
        }

        $manager->flush();
        $progress->finish();
        $table->render();
    }

    /**
     * @param ObjectManager $manager
     * @param ConsoleOutputInterface $out
     * @param array $cache
     * @param string $id
     * @param array $sub_cache
     * @return Requirement
     * @throws Exception
     */
    private function process_meta_requirement(        
        ObjectManager $manager, ConsoleOutputInterface $out,
        array &$cache, string $id, array &$sub_cache): Requirement
    {
        if (!isset($cache[$id])) {
            if (!isset(static::$item_actions['meta_requirements'][$id])) throw new Exception('Requirement definition not found: ' . $id);

            $data = static::$item_actions['meta_requirements'][$id];
            $requirement = $manager->getRepository(Requirement::class)->findOneByName( $id );
            if ($requirement) $out->writeln( "\t\t<comment>Update</comment> meta condition <info>$id</info>" );
            else {
                $requirement = new Requirement();
                $out->writeln( "\t\t<comment>Create</comment> meta condition <info>$id</info>" );
            }

            $requirement
                ->setName( $id )
                ->setFailureMode( $data['type'] )
                ->setFailureText( isset($data['text']) ? $data['text'] : null );

            foreach ($data['collection'] as $sub_id => $sub_req) {
                if (!isset( static::$item_actions['requirements'][$sub_id] ))
                    throw new Exception('Requirement type definition not found: ' . $sub_id);
                if (!isset( static::$item_actions['requirements'][$sub_id][$sub_req] ))
                    throw new Exception('Requirement entry definition not found: ' . $sub_id . '/' . $sub_req);

                $sub_data = static::$item_actions['requirements'][$sub_id][$sub_req];
                if (!isset($sub_cache[$sub_id])) $sub_cache[$sub_id] = [];
                                
                switch ($sub_id) {
                    case 'status':
                        $requirement->setStatusRequirement( $this->process_status_requirement( $manager, $out, $sub_cache[$sub_id], $sub_req, $sub_data ) );
                        break;
                    case 'item':
                        $requirement->setItem( $this->process_item_requirement($manager, $out, $sub_cache[$sub_id], $sub_req, $sub_data ) );
                        break;
                    default:
                        throw new Exception('No handler for requirement type ' . $sub_id);
                }
            }

            $manager->persist( $cache[$id] = $requirement );
        } else $out->writeln( "\t\t<comment>Skip</comment> meta condition <info>$id</info>" );
        
        return $cache[$id];
    }

    /**
     * @param ObjectManager $manager
     * @param ConsoleOutputInterface $out
     * @param array $cache
     * @param string $id
     * @param array $data
     * @return RequireStatus
     * @throws Exception
     */
    private function process_status_requirement(
        ObjectManager $manager, ConsoleOutputInterface $out, 
        array &$cache, string $id, array $data): RequireStatus
    {
        if (!isset($cache[$id])) {
            $requirement = $manager->getRepository(RequireStatus::class)->findOneByName( $id );
            if ($requirement) $out->writeln( "\t\t\t<comment>Update</comment> condition <info>status/{$id}</info>" );
            else {
                $requirement = new RequireStatus();
                $out->writeln( "\t\t\t<comment>Create</comment> condition <info>status/{$id}</info>" );
            }
            $status = $manager->getRepository(CitizenStatus::class)->findOneByName( $data['status'] );
            if (!$status)
                throw new Exception('Status condition not found: ' . $data['status']);

            $requirement->setName( $id )->setEnabled( $data['enabled'] )->setStatus( $status );
            $manager->persist( $cache[$id] = $requirement );
        } else $out->writeln( "\t\t\t<comment>Skip</comment> condition <info>status/{$id}</info>" );
        
        return $cache[$id];
    }

    /**
     * @param ObjectManager $manager
     * @param ConsoleOutputInterface $out
     * @param array $cache
     * @param string $id
     * @param array $data
     * @return RequireItem
     * @throws Exception
     */
    private function process_item_requirement(
        ObjectManager $manager, ConsoleOutputInterface $out,
        array &$cache, string $id, array $data): RequireItem
    {
        if (!isset($cache[$id])) {
            $requirement = $manager->getRepository(RequireItem::class)->findOneByName( $id );
            if ($requirement) $out->writeln( "\t\t\t<comment>Update</comment> condition <info>item/{$id}</info>" );
            else {
                $requirement = new RequireItem();
                $out->writeln( "\t\t\t<comment>Create</comment> condition <info>item/{$id}</info>" );
            }
            $prototype = empty($data['item']) ? null : $manager->getRepository(ItemPrototype::class)->findOneByName( $data['item'] );
            if (!empty($data['item']) && ! $prototype)
                throw new Exception('Item prototype not found: ' . $data['item']);

            $property  = empty($data['prop']) ? null : $manager->getRepository(ItemProperty::class )->findOneByName( $data['prop'] );
            if (!empty($data['prop']) && ! $property)
                throw new Exception('Item property not found: ' . $data['prop']);

            if (!$prototype && !$property)
                throw new Exception('Item condition must have a prototype or property attached. not found: ' . $data['status']);

            $requirement->setName( $id )->setPrototype( $prototype )->setProperty( $property );
            $manager->persist( $cache[$id] = $requirement );
        } else $out->writeln( "\t\t\t<comment>Skip</comment> condition <info>item/{$id}</info>" );

        return $cache[$id];
    }

    /**
     * @param ObjectManager $manager
     * @param ConsoleOutputInterface $out
     * @param array $cache
     * @param string $id
     * @param array $sub_cache
     * @return Result
     * @throws Exception
     */
    private function process_meta_effect(
        ObjectManager $manager, ConsoleOutputInterface $out,
        array &$cache, string $id, array &$sub_cache): Result
    {
        if (!isset($cache[$id])) {
            if (!isset(static::$item_actions['meta_results'][$id])) throw new Exception('Result definition not found: ' . $id);

            $data = static::$item_actions['meta_results'][$id];
            $result = $manager->getRepository(Result::class)->findOneByName( $id );
            if ($result) $out->writeln( "\t\t<comment>Update</comment> meta effect <info>$id</info>" );
            else {
                $result = new Result();
                $out->writeln( "\t\t<comment>Create</comment> meta effect <info>$id</info>" );
            }

            $result->setName( $id );

            foreach ($data['collection'] as $sub_id => $sub_res) {
                if (!isset( static::$item_actions['results'][$sub_id] ))
                    throw new Exception('Result type definition not found: ' . $sub_id);
                if (!isset( static::$item_actions['results'][$sub_id][$sub_res] ))
                    throw new Exception('Result entry definition not found: ' . $sub_id . '/' . $sub_res);

                $sub_data = static::$item_actions['results'][$sub_id][$sub_res];
                if (!isset($sub_cache[$sub_id])) $sub_cache[$sub_id] = [];

                switch ($sub_id) {
                    case 'status':
                        $result->setStatus( $this->process_status_effect($manager,$out,$sub_cache[$sub_id], $sub_res, $sub_data) );
                        break;
                    case 'ap':
                        $result->setAp( $this->process_ap_effect($manager,$out, $sub_cache[$sub_id], $sub_res, $sub_data) );
                        break;
                    case 'item':
                        $result->setItem( $this->process_item_effect($manager, $out, $sub_cache[$sub_id], $sub_res, $sub_data) );
                        break;
                    default:
                        throw new Exception('No handler for effect type ' . $sub_id);
                }
            }

            $manager->persist( $cache[$id] = $result );
        } else $out->writeln( "\t\t<comment>Skip</comment> meta effect <info>$id</info>" );

        return $cache[$id];
    }
    
    /**
     * @param ObjectManager $manager
     * @param ConsoleOutputInterface $out
     * @param array $cache
     * @param string $id
     * @param array $data
     * @return AffectStatus
     * @throws Exception
     */
    private function process_status_effect(
        ObjectManager $manager, ConsoleOutputInterface $out,
        array &$cache, string $id, array $data): AffectStatus
    {
        if (!isset($cache[$id])) {
            $result = $manager->getRepository(AffectStatus::class)->findOneByName( $id );
            if ($result) $out->writeln( "\t\t\t<comment>Update</comment> effect <info>status/{$id}</info>" );
            else {
                $result = new AffectStatus();
                $out->writeln( "\t\t\t<comment>Create</comment> effect <info>status/{$id}</info>" );
            }
            $status_from = empty($data['from']) ? null : $manager->getRepository(CitizenStatus::class)->findOneByName( $data['from'] );
            if (!$status_from && !empty($data['from'])) throw new Exception('Status effect not found: ' . $data['from']);
            $status_to = empty($data['to']) ? null : $manager->getRepository(CitizenStatus::class)->findOneByName( $data['to'] );
            if (!$status_to && !empty($data['to'])) throw new Exception('Status effect not found: ' . $data['to']);

            if (!$status_from && !$status_to) throw new Exception('Status effects must have at least one attached status.');

            $result->setName( $id )->setInitial( $status_from )->setResult( $status_to );
            $manager->persist( $cache[$id] = $result );
        } else $out->writeln( "\t\t\t<comment>Skip</comment> condition <info>status/{$id}</info>" );
        
        return $cache[$id];
    }

    /**
     * @param ObjectManager $manager
     * @param ConsoleOutputInterface $out
     * @param array $cache
     * @param string $id
     * @param array $data
     * @return AffectAP
     */
    private function process_ap_effect(
        ObjectManager $manager, ConsoleOutputInterface $out,
        array &$cache, string $id, array $data): AffectAP
    {
        if (!isset($cache[$id])) {
            $result = $manager->getRepository(AffectAP::class)->findOneByName( $id );
            if ($result) $out->writeln( "\t\t\t<comment>Update</comment> effect <info>ap/{$id}</info>" );
            else {
                $result = new AffectAP();
                $out->writeln( "\t\t\t<comment>Create</comment> effect <info>ap/{$id}</info>" );
            }

            $result->setName( $id )->setMax( $data['max'] )->setAp( $data['num'] );
            $manager->persist( $cache[$id] = $result );
        } else $out->writeln( "\t\t\t<comment>Skip</comment> condition <info>ap/{$id}</info>" );
        
        return $cache[$id];
    }

    /**
     * @param ObjectManager $manager
     * @param ConsoleOutputInterface $out
     * @param array $cache
     * @param string $id
     * @param array $data
     * @return AffectOriginalItem
     * @throws Exception
     */
    private function process_item_effect(
        ObjectManager $manager, ConsoleOutputInterface $out,
        array &$cache, string $id, array $data): AffectOriginalItem 
    {
        if (!isset($cache[$id])) {
            $result = $manager->getRepository(AffectOriginalItem::class)->findOneByName( $id );
            if ($result) $out->writeln( "\t\t\t<comment>Update</comment> effect <info>item/{$id}</info>" );
            else {
                $result = new AffectOriginalItem();
                $out->writeln( "\t\t\t<comment>Create</comment> effect <info>item/{$id}</info>" );
            }
            $morph_to = empty($data['morph']) ? null : $manager->getRepository(ItemPrototype::class)->findOneByName( $data['morph'] );
            if (!$morph_to && !empty($data['morph'])) throw new Exception('Item prototype not found: ' . $data['morph']);

            if ($morph_to && $data['consume']) throw new Exception('Item effects cannot morph and consume at the same time!');

            $result->setName( $id )->setConsume( $data['consume'] )->setMorph( $morph_to );
            $manager->persist( $cache[$id] = $result );
        } else $out->writeln( "\t\t\t<comment>Skip</comment> condition <info>item/{$id}</info>" );
        
        return $cache[$id];
    } 
    
    /**
     * @param ObjectManager $manager
     * @param ConsoleOutputInterface $out
     * @throws Exception
     */
    public function insert_item_actions(ObjectManager $manager, ConsoleOutputInterface $out) {

        $out->writeln( '<comment>Compiling item action fixtures.</comment>' );

        $set_meta_requirements = [];
        $set_sub_requirements = [];

        $set_meta_results = [];
        $set_sub_results = [];

        $set_actions = [];

        foreach (static::$item_actions['items'] as $item_name => $actions) {

            $item = $manager->getRepository(ItemPrototype::class)->findOneByName( $item_name );
            if (!$item) throw new Exception('Item prototype not found: ' . $item_name);
            $out->writeln( "Compiling action set for item <info>{$item->getLabel()}</info>..." );

            foreach ($actions as $action) {

                if (!isset($set_actions[$action])) {
                    if (!isset(static::$item_actions['actions'][$action])) throw new Exception('Action definition not found: ' . $action);

                    $data = static::$item_actions['actions'][$action];
                    $new_action = $manager->getRepository(ItemAction::class)->findOneByName( $action );
                    if ($new_action) $out->writeln( "\t<comment>Update</comment> action <info>$action</info> ('<info>{$data['label']}</info>')" );
                    else {
                        $new_action = new ItemAction();
                        $out->writeln( "\t<comment>Create</comment> action <info>$action</info> ('<info>{$data['label']}</info>')" );
                    }

                    $new_action->setName( $action )->setLabel( $data['label'] )->clearRequirements();

                    foreach ( $data['meta'] as $requirement )
                        $new_action->addRequirement( $this->process_meta_requirement( $manager, $out, $set_meta_requirements, $requirement, $set_sub_requirements ) );

                    foreach ( $data['result'] as $result )
                        $new_action->addResult( $this->process_meta_effect($manager,$out, $set_meta_results, $result, $set_sub_results) );

                    $manager->persist( $set_actions[$action] = $new_action );
                } else $out->writeln( "\t<comment>Skip</comment> action <info>$action</info> ('<info>{$set_actions[$action]->getLabel()}</info>')" );

                $item->addAction( $set_actions[$action] );
            }
            $manager->persist( $item );
        }
        $manager->flush();
    }

    public function load(ObjectManager $manager) {

        $output = new ConsoleOutput();
        $output->writeln( '<info>Installing fixtures: Item Database</info>' );
        $output->writeln("");

        $this->insert_item_categories( $manager, $output );
        $output->writeln("");
        $this->insert_item_prototypes( $manager, $output );
        $output->writeln("");

        $output->writeln( '<info>Installing fixtures: Citizen Database</info>' );
        $output->writeln("");

        $this->insert_professions( $manager, $output );
        $output->writeln("");
        $this->insert_status_types( $manager, $output );
        $output->writeln("");

        $output->writeln( '<info>Installing fixtures: Town Content Database</info>' );
        $output->writeln("");

        $this->insert_town_classes( $manager, $output );
        $output->writeln("");

        $output->writeln( '<info>Installing fixtures: Actions</info>' );
        $output->writeln("");

        try {
            $this->insert_item_actions( $manager, $output );
        } catch (Exception $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");
        }

        $output->writeln("");
    }
}
