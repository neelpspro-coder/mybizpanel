<?php
/**
 * Système BB Code pour MyBizPanel
 * Convertit les codes BB en HTML avec style pour les fiches blog
 */

function parseBBCode($text) {
    if (empty($text)) return '';
    
    // Échapper les caractères HTML d'abord
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    
    // === FORMATAGE DE BASE ===
    // Gras [b]texte[/b]
    $text = preg_replace('/\[b\](.*?)\[\/b\]/i', '<strong class="font-bold">$1</strong>', $text);
    
    // Italique [i]texte[/i]
    $text = preg_replace('/\[i\](.*?)\[\/i\]/i', '<em class="italic">$1</em>', $text);
    
    // Souligné [u]texte[/u]
    $text = preg_replace('/\[u\](.*?)\[\/u\]/i', '<span class="underline">$1</span>', $text);
    
    // Barré [s]texte[/s]
    $text = preg_replace('/\[s\](.*?)\[\/s\]/i', '<span class="line-through">$1</span>', $text);
    
    // === COULEURS ===
    // [color=rouge]texte[/color] ou [color=#ff0000]texte[/color]
    $text = preg_replace_callback('/\[color=([a-zA-Z]+|#[0-9a-fA-F]{6})\](.*?)\[\/color\]/i', function($matches) {
        $color = $matches[1];
        $content = $matches[2];
        
        // Couleurs prédéfinies
        $colors = [
            'rouge' => 'text-red-600',
            'vert' => 'text-green-600',
            'bleu' => 'text-blue-600',
            'jaune' => 'text-yellow-600',
            'orange' => 'text-orange-600',
            'violet' => 'text-purple-600',
            'rose' => 'text-pink-600',
            'gris' => 'text-gray-600',
            'noir' => 'text-black dark:text-white',
            'blanc' => 'text-white dark:text-black'
        ];
        
        if (isset($colors[strtolower($color)])) {
            return '<span class="' . $colors[strtolower($color)] . '">' . $content . '</span>';
        } elseif (preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            return '<span style="color: ' . $color . '">' . $content . '</span>';
        }
        
        return $content;
    }, $text);
    
    // === TAILLES ===
    // [size=petit]texte[/size]
    $text = preg_replace_callback('/\[size=(petit|normal|grand|titre)\](.*?)\[\/size\]/i', function($matches) {
        $size = strtolower($matches[1]);
        $content = $matches[2];
        
        $sizes = [
            'petit' => 'text-sm',
            'normal' => 'text-base',
            'grand' => 'text-lg',
            'titre' => 'text-xl font-bold'
        ];
        
        return '<span class="' . ($sizes[$size] ?? 'text-base') . '">' . $content . '</span>';
    }, $text);
    
    // === LISTES ===
    // [list][*]item1[*]item2[/list]
    $text = preg_replace_callback('/\[list\](.*?)\[\/list\]/s', function($matches) {
        $content = $matches[1];
        $items = preg_split('/\[\*\]/', $content);
        array_shift($items); // Supprimer le premier élément vide
        
        $html = '<ul class="list-disc list-inside ml-4 space-y-1">';
        foreach ($items as $item) {
            $item = trim($item);
            if (!empty($item)) {
                $html .= '<li>' . $item . '</li>';
            }
        }
        $html .= '</ul>';
        
        return $html;
    }, $text);
    
    // === LIENS ===
    // [url=https://example.com]texte[/url] ou [url]https://example.com[/url]
    $text = preg_replace('/\[url=(https?:\/\/[^\]]+)\](.*?)\[\/url\]/i', '<a href="$1" class="text-blue-600 hover:text-blue-800 underline" target="_blank" rel="noopener">$2</a>', $text);
    $text = preg_replace('/\[url\](https?:\/\/[^\[]+)\[\/url\]/i', '<a href="$1" class="text-blue-600 hover:text-blue-800 underline" target="_blank" rel="noopener">$1</a>', $text);
    
    // === IMAGES ===
    // [img]url[/img]
    $text = preg_replace('/\[img\](https?:\/\/[^\[]+)\[\/img\]/i', '<img src="$1" alt="Image" class="max-w-full h-auto rounded-lg shadow-md my-2">', $text);
    
    // === CITATIONS ===
    // [quote]texte[/quote] ou [quote=auteur]texte[/quote]
    $text = preg_replace('/\[quote=([^\]]+)\](.*?)\[\/quote\]/s', '<blockquote class="border-l-4 border-blue-500 pl-4 py-2 my-4 bg-gray-50 dark:bg-gray-800"><cite class="text-sm font-semibold text-blue-600">$1 :</cite><br>$2</blockquote>', $text);
    $text = preg_replace('/\[quote\](.*?)\[\/quote\]/s', '<blockquote class="border-l-4 border-gray-400 pl-4 py-2 my-4 bg-gray-50 dark:bg-gray-800 italic">$1</blockquote>', $text);
    
    // === CODE ===
    // [code]code[/code]
    $text = preg_replace('/\[code\](.*?)\[\/code\]/s', '<pre class="bg-gray-900 text-green-400 p-4 rounded-lg overflow-x-auto my-4"><code>$1</code></pre>', $text);
    
    // Code inline [c]code[/c]
    $text = preg_replace('/\[c\](.*?)\[\/c\]/i', '<code class="bg-gray-200 dark:bg-gray-700 px-2 py-1 rounded text-sm font-mono">$1</code>', $text);
    
    // === TABLEAUX SIMPLES ===
    // [table][tr][td]cellule[/td][td]cellule[/td][/tr][/table]
    $text = preg_replace('/\[table\](.*?)\[\/table\]/s', '<table class="w-full border-collapse border border-gray-300 dark:border-gray-600 my-4">$1</table>', $text);
    $text = preg_replace('/\[tr\](.*?)\[\/tr\]/s', '<tr class="border-b border-gray-300 dark:border-gray-600">$1</tr>', $text);
    $text = preg_replace('/\[td\](.*?)\[\/td\]/s', '<td class="border border-gray-300 dark:border-gray-600 px-3 py-2">$1</td>', $text);
    $text = preg_replace('/\[th\](.*?)\[\/th\]/s', '<th class="border border-gray-300 dark:border-gray-600 px-3 py-2 bg-gray-100 dark:bg-gray-700 font-semibold">$1</th>', $text);
    
    // === ALIGNEMENT ===
    $text = preg_replace('/\[center\](.*?)\[\/center\]/s', '<div class="text-center">$1</div>', $text);
    $text = preg_replace('/\[right\](.*?)\[\/right\]/s', '<div class="text-right">$1</div>', $text);
    $text = preg_replace('/\[left\](.*?)\[\/left\]/s', '<div class="text-left">$1</div>', $text);
    
    // === CADRES SPÉCIAUX ===
    // [info]texte[/info] - Cadre d'information
    $text = preg_replace('/\[info\](.*?)\[\/info\]/s', '<div class="bg-blue-50 dark:bg-blue-900/20 border-l-4 border-blue-500 p-4 my-4"><div class="flex"><i class="fas fa-info-circle text-blue-500 mr-2 mt-1"></i><div>$1</div></div></div>', $text);
    
    // [warning]texte[/warning] - Cadre d'avertissement
    $text = preg_replace('/\[warning\](.*?)\[\/warning\]/s', '<div class="bg-yellow-50 dark:bg-yellow-900/20 border-l-4 border-yellow-500 p-4 my-4"><div class="flex"><i class="fas fa-exclamation-triangle text-yellow-500 mr-2 mt-1"></i><div>$1</div></div></div>', $text);
    
    // [error]texte[/error] - Cadre d'erreur
    $text = preg_replace('/\[error\](.*?)\[\/error\]/s', '<div class="bg-red-50 dark:bg-red-900/20 border-l-4 border-red-500 p-4 my-4"><div class="flex"><i class="fas fa-times-circle text-red-500 mr-2 mt-1"></i><div>$1</div></div></div>', $text);
    
    // [success]texte[/success] - Cadre de succès
    $text = preg_replace('/\[success\](.*?)\[\/success\]/s', '<div class="bg-green-50 dark:bg-green-900/20 border-l-4 border-green-500 p-4 my-4"><div class="flex"><i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i><div>$1</div></div></div>', $text);
    
    // === SPOILER ===
    // [spoiler]texte[/spoiler] ou [spoiler=titre]texte[/spoiler]
    $spoilerCounter = 0;
    $text = preg_replace_callback('/\[spoiler(?:=([^\]]+))?\](.*?)\[\/spoiler\]/s', function($matches) use (&$spoilerCounter) {
        $spoilerCounter++;
        $title = isset($matches[1]) ? $matches[1] : 'Spoiler';
        $content = $matches[2];
        
        return '<div class="my-4">
            <button onclick="toggleSpoiler(' . $spoilerCounter . ')" class="bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 px-4 py-2 rounded-lg font-semibold">
                <i class="fas fa-eye-slash mr-2"></i>' . $title . '
            </button>
            <div id="spoiler-' . $spoilerCounter . '" class="hidden mt-2 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">' . $content . '</div>
        </div>';
    }, $text);
    
    // === RETOURS À LA LIGNE ===
    $text = str_replace("\n", '<br>', $text);
    
    return $text;
}

// JavaScript pour les spoilers (à inclure dans la page)
function getBBCodeJS() {
    return '
    <script>
    function toggleSpoiler(id) {
        const spoiler = document.getElementById("spoiler-" + id);
        const button = spoiler.previousElementSibling;
        const icon = button.querySelector("i");
        
        if (spoiler.classList.contains("hidden")) {
            spoiler.classList.remove("hidden");
            icon.className = "fas fa-eye mr-2";
        } else {
            spoiler.classList.add("hidden");
            icon.className = "fas fa-eye-slash mr-2";
        }
    }
    </script>';
}

// Guide d'utilisation des BB Codes
function getBBCodeGuide() {
    return [
        'Formatage de base' => [
            '[b]Gras[/b]' => 'Texte en gras',
            '[i]Italique[/i]' => 'Texte en italique',
            '[u]Souligné[/u]' => 'Texte souligné',
            '[s]Barré[/s]' => 'Texte barré'
        ],
        'Couleurs et tailles' => [
            '[color=rouge]Texte rouge[/color]' => 'Couleur prédéfinie',
            '[color=#ff0000]Texte rouge[/color]' => 'Couleur hexadécimale',
            '[size=grand]Texte large[/size]' => 'Tailles: petit, normal, grand, titre'
        ],
        'Listes et liens' => [
            '[list][*]Item 1[*]Item 2[/list]' => 'Liste à puces',
            '[url=https://example.com]Lien[/url]' => 'Lien avec texte',
            '[url]https://example.com[/url]' => 'Lien simple'
        ],
        'Médias' => [
            '[img]https://example.com/image.jpg[/img]' => 'Image',
            '[quote=Auteur]Citation[/quote]' => 'Citation avec auteur',
            '[quote]Citation simple[/quote]' => 'Citation simple'
        ],
        'Code' => [
            '[code]Code multiligne[/code]' => 'Bloc de code',
            '[c]code inline[/c]' => 'Code dans le texte'
        ],
        'Cadres spéciaux' => [
            '[info]Information[/info]' => 'Cadre d\'information',
            '[warning]Attention[/warning]' => 'Cadre d\'avertissement',
            '[error]Erreur[/error]' => 'Cadre d\'erreur',
            '[success]Succès[/success]' => 'Cadre de succès'
        ],
        'Autres' => [
            '[spoiler=Titre]Contenu caché[/spoiler]' => 'Contenu masqué',
            '[center]Centré[/center]' => 'Texte centré'
        ]
    ];
}
?>