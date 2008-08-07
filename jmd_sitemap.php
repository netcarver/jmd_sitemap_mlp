<?php
$plugin['name'] = 'jmd_sitemap_mlp';
$plugin['version'] = '0.1.2';
$plugin['author'] = 'Jon-Michael Deldin';
$plugin['author_uri'] = 'http://jmdeldin.com';
$plugin['description'] = 'Generates a sitemap.';
$plugin['type'] = '1';
$plugin['order'] = 5;

@include_once('../zem_tpl.php');

# --- BEGIN PLUGIN CODE ---

#
#	Define a unique prefix for our strings (make sure there is no '-' in it!)
#
if( !defined( 'JMD_SITEMAP_PREFIX' ) )
	define( 'JMD_SITEMAP_PREFIX' , 'jmd_sitemap' );

#===============================================================================
#	Strings for internationalisation...
#===============================================================================
global $_jmd_sitemap_l18n;
$_jmd_sitemap_l18n = array(
	'extension_tab'			=> 'JMD Sitemap',
	'sitemap_updated' 		=> 'Sitemap updated',
	'error_not_writable' 	=> 'is not writable. Please chmod it to 666.',
	'error_doesnt_exist'	=> 'does not exist. Please create it and chmod it to 666.',
	'update_sitemap'		=> 'Update sitemap',
	'exclude_sections'		=> 'Exclude sections',
	);

function _jmd_sitemap_gtxt( $what , $args=array() )
	{
	global $textarray;
	global $_jmd_sitemap_l18n;

	$key = JMD_SITEMAP_PREFIX . '-' . $what;
	$key = strtolower($key);

	if(isset($textarray[$key]))
		$str = $textarray[$key];
	else
		{
		$key = strtolower($what);

		if( isset( $_jmd_sitemap_l18n[$key] ) )
			$str = $_jmd_sitemap_l18n[$key];
		else
			$str = $what;
		}
	$str = strtr( $str , $args );
	return $str;
	}

#===============================================================================
#	MLP Registration...
#===============================================================================
register_callback( '_jmd_sitemap_enumerate_strings' , 'l10n.enumerate_strings' );
function _jmd_sitemap_enumerate_strings()
	{
	global $_jmd_sitemap_l18n;
	$r = array	(
				'owner'		=> 'jmd_sitemap',
				'prefix'	=> JMD_SITEMAP_PREFIX,
				'lang'		=> 'en-gb',
				'event'		=> 'admin',
				'strings'	=> $_jmd_sitemap_l18n,
				);
	return $r;
	}

#===============================================================================
#	Admin interface features...
#===============================================================================
if (txpinterface == 'admin')
	{
    add_privs('jmd_sitemap', 1);
    register_tab('extensions', 'jmd_sitemap', _jmd_sitemap_gtxt('extension_tab'));
    register_callback('jmd_sitemap', 'jmd_sitemap');
    register_callback('jmd_sitemap', 'article', ('create' || 'edit'));
    if (empty($GLOBALS['prefs']['jmd_sitemap_exclude']))
    	{
        $tmp = serialize(array('default'));
        $GLOBALS['prefs']['jmd_sitemap_exclude'] = $tmp;
        safe_insert("txp_prefs", "prefs_id = 1,
            name = 'jmd_sitemap_exclude',
            val = '$tmp',
            type = 2,
            event = 'admin',
            html = 'text_input',
            position = 0
        ");
		}
	}

function jmd_sitemap($event, $step)
	{
    global $prefs;
    $sitemap = new JMD_Sitemap();

    // Generate sitemap
    if ($step == ( 'create' || 'edit' || 'update'))
    	{
        $excluded = gps('exclude');
        if ($excluded)
        	{
            $excluded = serialize($excluded);
            $prefs['jmd_sitemap_exclude'] = $excluded;
            safe_update("txp_prefs", "val = '$excluded'",
                "name = 'jmd_sitemap_exclude'"
            );
			}
        $sitemap->writeSitemap();
		}

    // Extensions tab
    if ($event == 'jmd_sitemap')
    	{
        pageTop('jmd_sitemap', ($step ? _jmd_sitemap_gtxt('sitemap_updated') : ''));
        echo '<div id="jmd_sitemap" style="width: 350px; margin: 0 auto">';

        // File errors
        if (file_exists($sitemap->filename))
        	{
            if (!is_writable($sitemap->filename))
                $fileError = _jmd_sitemap_gtxt('error_not_writable');
			}
        else
            $fileError = _jmd_sitemap_gtxt('error_doesnt_exist');
        if (isset($fileError))
            echo tag($sitemap->filename . ' ' . $fileError, 'p', ' class="not-ok"');

        $out = '<label for="exclude">'._jmd_sitemap_gtxt('exclude_sections').':</label><br/>
            <select id="exclude" name="exclude[]" multiple="multiple"
                size="5" style="width: 150px; margin: 3px 0 10px">';

        // Exclude sections
        $exclude = $prefs['jmd_sitemap_exclude'];
        $exclude = unserialize($exclude);
        $sections = safe_column("name", "txp_section", "name != 'default'");
        foreach ($sections as $section)
	        {
            $out .= '<option name="'. $section .'"';
            // Select excluded
            if (in_array($section, $exclude))
                $out .= ' selected="selected"';

            $out .= ">$section</option>";
			}
        $out .= '</select><br/>';
        echo form($out . tag( _jmd_sitemap_gtxt('update_sitemap'), 'button') .
            eInput('jmd_sitemap') . sInput('update')
				 );

        echo '</div><!--//jmd_sitemap-->';
		}
	}


class JMD_Sitemap
	{
    public $filename;

    public function __construct()
    	{
        $this->filename = dirname(txpath) . DS .'sitemap.xml.gz';
		}

    // Generate <url> XML
    public function urlXML($loc, $lastmod='')
    	{
        $out = '<url>';
        $out .= "<loc>$loc</loc>";
        if ($lastmod)
            $out .= "<lastmod>$lastmod</lastmod>";
        $out .= '</url>';

        return $out;
		}

    // Generate sitemap XML
    public function sitemapXML()
    	{
		static $mlp_installed;

		if( !isset( $mlp_installed ) )
			$mlp_installed = is_callable( 'l10n_installed' ) ? call_user_func('l10n_installed',true) : false ;

        $out = '<?xml version="1.0" encoding="utf-8"?>';
        $out .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        // Homepage
        $out .= $this->urlXML(hu);

        // Excluded sections
        $excluded = $GLOBALS['prefs']['jmd_sitemap_exclude'];
        $excluded = unserialize($excluded);
        foreach ($excluded as $key => $value)
            $notIn[$key] = "'$value'";
        $notIn = implode(',', $notIn);
        $notIn .= ",'default'";

        // List sections

		$site_langs = array();
		if ($mlp_installed)
			$site_langs = MLPLanguageHandler::get_site_langs();

        $sections = safe_column("name", "txp_section", "name not in($notIn)");
        foreach ($sections as $section)
        	{
			if ($mlp_installed)
				{
				foreach( $site_langs as $lang )
					{
					$lang = substr($lang,0,2) . '/';
					$loc = hu.$lang.urlencode($section).'/';
					$out .= $this->urlXML($loc);
					}
				}
			else
				{
				$loc = pagelinkurl(array('s' => $section));
				$out .= $this->urlXML($loc);
				}
        	}

        // Articles
		$fields = 'ID as thisid, Section as section, Title as title,
            url_title, unix_timestamp(Posted) as posted,
            unix_timestamp(LastMod) as lastmod';

		if ($mlp_installed) $fields .= ', l10n_lang';

        $articles = getRows(
			"select $fields from " .
			safe_pfx('textpattern') .
			" where Status = 4 and Posted<= now() and section not in($notIn)"
			);
        if ($articles)
        	{
            include_once txpath . '/publish/taghandlers.php';
            foreach ($articles as $article)
            	{
				$loc = permlinkurl($article);
				if ($mlp_installed)
					$loc = str_replace(hu, hu . substr($article['l10n_lang'],0,2) . '/', $loc);
				$lastmod = date('c', $article['lastmod']);
				$out .= $this->urlXML($loc, $lastmod);
            	}
       		}
        $out .= '</urlset>';

        return $out;
		}

    // Write gzipped sitemap
    public function writeSitemap()
    	{
        $sitemap = gzopen($this->filename, 'wb');
        gzwrite($sitemap, $this->sitemapXML());
        gzclose($sitemap);
    	}
	}

# --- END PLUGIN CODE ---

/*
# --- BEGIN PLUGIN CSS ---
	<style type="text/css">
	div#jmd_sitemap_help td { vertical-align:top; }
	div#jmd_sitemap_help code { font-weight:bold; font: 105%/130% "Courier New", courier, monospace; background-color: #FFFFCC;}
	div#jmd_sitemap_help code.sed_code_tag { font-weight:normal; border:1px dotted #999; background-color: #f0e68c; display:block; margin:10px 10px 20px; padding:10px; }
	div#jmd_sitemap_help a:link, div#jmd_sitemap_help a:visited { color: blue; text-decoration: none; border-bottom: 1px solid blue; padding-bottom:1px;}
	div#jmd_sitemap_help a:hover, div#jmd_sitemap_help a:active { color: blue; text-decoration: none; border-bottom: 2px solid blue; padding-bottom:1px;}
	div#jmd_sitemap_help h1 { color: #369; font: 20px Georgia, sans-serif; margin: 0; text-align: center; }
	div#jmd_sitemap_help h2 { border-bottom: 1px solid black; padding:10px 0 0; color: #369; font: 17px Georgia, sans-serif; }
	div#jmd_sitemap_help h3 { color: #693; font: bold 12px Arial, sans-serif; letter-spacing: 1px; margin: 10px 0 0;text-transform: uppercase;}
	div#jmd_sitemap_help ul ul { font-size:85%; }
	div#jmd_sitemap_help h3 { color: #693; font: bold 12px Arial, sans-serif; letter-spacing: 1px; margin: 10px 0 0;text-transform: uppercase;}
	</style>
# --- END PLUGIN CSS ---
# --- BEGIN PLUGIN HELP ---
<div id="jmd_sitemap_help">

h1(#top). Important

You must create and chmod 666 a sitemap.xml.gz file in your web root.

$ touch sitemap.xml.gz; chmod 666 sitemap.xml.gz

h2(#changelog). Change Log

v0.2

* Integration with the MLP Pack for multi-lingual sites.

v0.1

* Initial release by Jon-Michael Deldin

 <span style="float:right"><a href="#top" title="Jump to the top">top</a></span>

</div>
# --- END PLUGIN HELP ---
*/
?>
