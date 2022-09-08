<?php

// Fetch relevant IA files

error_reporting(E_ALL);

$config['cache']   = dirname(__FILE__) . '/cache';

//----------------------------------------------------------------------------------------
function head($url)
{
	$opts = array(
	  CURLOPT_URL =>$url,
	  CURLOPT_FOLLOWLOCATION => TRUE,
	  CURLOPT_RETURNTRANSFER => TRUE,
	  CURLOPT_HEADER		 => TRUE,
	  CURLOPT_NOBODY		 => TRUE
	);
	
	$ch = curl_init();
	curl_setopt_array($ch, $opts);
	$data = curl_exec($ch);
	$info = curl_getinfo($ch); 
	curl_close($ch);

	$http_code = $info['http_code'];
		
	return ($http_code == 200);
}

//----------------------------------------------------------------------------------------
function get($url, $format = '')
{
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	
	if ($format != '')
	{
		curl_setopt($ch, CURLOPT_HTTPHEADER, array("Accept: " . $format));	
	}
	
	$response = curl_exec($ch);
	if($response == FALSE) 
	{
		$errorText = curl_error($ch);
		curl_close($ch);
		die($errorText);
	}
	
	$info = curl_getinfo($ch);
	$http_code = $info['http_code'];
	
	if ($http_code == 404)
	{
		$response = '';
	}
	
	//echo $http_code . "\n";
	
	curl_close($ch);
	
	return $response;
}

//----------------------------------------------------------------------------------------
function get_cache_directory($ia)
{
	global $config;
	
	$dir = $config['cache'] . '/' . $ia;

	if (!file_exists($dir))
	{
		$oldumask = umask(0); 
		mkdir($dir, 0777);
		umask($oldumask);
	}
	
	return $dir;	
}

//----------------------------------------------------------------------------------------
function clean_identifier($identifier)
{
	$identifier = str_replace(' ', '', $identifier);
	return $identifier;
}


//----------------------------------------------------------------------------------------
// Meta may have a diffeent file name from the identifier
function get_meta_json($identifier)
{
	$json = '';
		
	$dir = get_cache_directory($identifier);	
	
	$filename = $dir. '/' . $identifier . '.json';
	
	if (!file_exists($filename))
	{
		$url = 'https://archive.org/metadata/' . clean_identifier($identifier);	
		$json = get($url);	
		file_put_contents($filename, $json);
	}
	
	$json = file_get_contents($filename);
	
	return $json;
}


//----------------------------------------------------------------------------------------
function metadata_to_csl($metadata)
{
	$csl = new stdclass;
	
	// by default assume it is a book, either an actual book or a book-like thing that has been scanned
	// if we have a journal and/or ISSN then we have an article.
	$csl->type = 'book';
	
	$keys = array('identifier', 'title', 'creator', 'journaltitle', 'issn', 'volume', 'pages', 'identifier-access', 'identifier-ark', 'external-identifier');
	
	foreach ($keys as $k)
	{
		if (isset($metadata->$k))
		{
			switch ($k)
			{
				case 'identifier':
					$csl->id =$metadata->{$k};
					break;	
					
				case 'identifier-access':
					$csl->URL =$metadata->{$k};
					break;			

				case 'identifier-ark':
					if (preg_match('/^ark:\/(.*)/',$metadata->{$k}, $m))
					{
						$csl->ARK =  $m[1];
					}	
					break;										

				case 'external-identifier':
					$values = array();
					if (is_array($metadata->{$k}))
					{	
						$values = $metadata->{$k};
					}
					else
					{
						$values[] = $metadata->{$k};
					}
					
					foreach ($values as $value)
					{
						if (preg_match('/^(urn:)?doi:(?<doi>.*)/', $value, $m))
						{
							$doi = strtolower($m['doi']);			
							$csl->DOI = $doi;
						}	

						if (preg_match('/^urn:jstor-articleid:(?<doi>10.2307\/(?<jstor>\d+))$/', $value, $m))
						{
							$doi = strtolower($m['doi']);			
							$csl->DOI = $doi;
							
							$csl->JSTOR = $m['jstor'];
							
						}	

					}
					break;		
					
				case 'issn':
					if (preg_match('/^([0-9]{4}-[0-9]{3}[0-9X])$/',$metadata->{$k}))
					{
						$csl->ISSN[] =$metadata->{$k};
					}

					// jstor
					if (preg_match('/^([0-9]{4}[0-9]{3}[0-9X])$/',$metadata->{$k}))
					{
						$csl->ISSN[] = substr($metadata->{$k}, 0, 4) . '-' . substr($metadata->{$k}, 4);
					}					

					if (preg_match('/([0-9]{4}-[0-9]{3}[0-9X])\s+\(Print\)\s+([0-9]{4}-[0-9]{3}[0-9X])\s+\(Electronic\)/',$metadata->{$k}, $m))		
					{
						$csl->ISSN[] = $m[1];
						$csl->ISSN[] = $m[2];
					}
					break;			
					
				case 'journaltitle':
					$csl->{'container-title'} = $metadata->{$k};
					$csl->type = "article-journal";
					break;			
					
						
				case 'pages':
					$csl->page =$metadata->{$k};
					break;			
			
				case 'title':
				case 'volume':
					$csl->{$k} = $metadata->{$k};
					break;
					
				case 'creator':
					$values = array();
					if (is_array($metadata->{$k}))
					{	
						$values = $metadata->{$k};
					}
					else
					{
						$values[] = $metadata->{$k};
					}
					
					foreach ($values as $value)
					{
						$author = new stdclass;
						$author->literal = $value;
						$csl->author[] = $author;
					}					
					break;
					
				default:
					break;			
			}
		}
	}
	
	if (isset($metadata->date))
	{
		$parts = explode('-', $metadata->date);
		
		$csl->issued = new stdclass;
		$csl->issued->{'date-parts'} = array();
		$csl->issued->{'date-parts'}[0] = array();
		
		$csl->issued->{'date-parts'}[0][] = (Integer)$parts[0];

		if (isset($parts[1]) && $parts[1] != '00')
		{		
			$csl->issued->{'date-parts'}[0][] = (Integer)$parts[1];
		}

		if (isset($parts[2]) && $parts[2] != '00')
		{		
			$csl->issued->{'date-parts'}[0][] = (Integer)$parts[2];
		}	
	}
	
	if (isset($metadata->year) && !isset($csl->issued))
	{
		$csl->issued = new stdclass;
		$csl->issued->{'date-parts'} = array();
		$csl->issued->{'date-parts'}[0] = array();
	
		$csl->issued->{'date-parts'}[0][] = (Integer)($metadata->year);		
	}	
		
	return $csl;
}

//----------------------------------------------------------------------------------------
// Use IA API to get metadata
function get_metadata($identifier)
{
	$json = get_meta_json($identifier);	
	$obj = json_decode($json);	
	return $obj;
}

//----------------------------------------------------------------------------------------
function get_scandata($metadata)
{
	$xml = '';
	
	//print_r($metadata->files);
	
	$filename = '';
	foreach ($metadata->files as $file)
	{
		if ($file->format == 'Scandata')
		{
			$filename = $file->name;		
		}
	}
	
	if ($filename != '')
	{
		$dir = get_cache_directory($metadata->metadata->identifier);	
	
		$xml_filename = $dir. '/' . $filename;
	
		if (!file_exists($xml_filename))
		{
			$url = 'https://archive.org/download/' . clean_identifier($metadata->metadata->identifier) . '/' . rawurlencode($filename);
			$xml = get($url);	
			file_put_contents($xml_filename, $xml);
		}
	
		$xml = file_get_contents($xml_filename);
	}

	return $xml;

}

//----------------------------------------------------------------------------------------
function get_pages_scandata($xml) 
{
	$pages = array();

	$dom = new DOMDocument;
	$dom->loadXML($xml);
	$xpath = new DOMXPath($dom);
	
	// Sometimes (e.g., mobot31753002456132) some pages have zero width and height
	// in the XML, so we keep some default values handy for these cases
	$width 	= 1000;
	$height = 1000;
		
	$nodeCollection = $xpath->query ('/book/pageData/page');
	foreach($nodeCollection as $node)
	{
		$page = new stdclass;
		
		if ($node->hasAttributes()) 
		{ 
			$attributes = array();
			$attrs = $node->attributes; 
			
			foreach ($attrs as $i => $attr)
			{
				$attributes[$attr->name] = $attr->value; 
			}
			
			$page->leafNum = (Integer)$attributes['leafNum'];
		}
		
		$nc = $xpath->query ('origWidth', $node);
		foreach($nc as $n)
		{
			$page->width = (Integer)$n->firstChild->nodeValue;
			
			if ($page->width == 0)
			{
				$page->width = $width;
			}
			else
			{
				$width = $page->width;
			}
		}

		$nc = $xpath->query ('origHeight', $node);
		foreach($nc as $n)
		{
			$page->height = (Integer) $n->firstChild->nodeValue;
			
			if ($page->height == 0)
			{
				$page->height = $height;
			}
			else
			{
				$height = $page->height;
			}			
		}
		
		$nc = $xpath->query ('pageNumber', $node);
		foreach($nc as $n)
		{
			$page->pageNumber = $n->firstChild->nodeValue;
		}
		
		// insert in order we encounter pages (can't rely on leafNum being correct)
		$pages[] = $page;
	
	}
	
	return $pages;	
}

//----------------------------------------------------------------------------------------
// Augment pages based on BHL METS
function get_bhl_pages($xml, $pages) 
{
	$dom = new DOMDocument;
	$dom->loadXML($xml);
	$xpath = new DOMXPath($dom);

	$xpath->registerNamespace("mets", "http://www.loc.gov/METS/");
	
	$nodeCollection = $xpath->query ('//mets:div[@TYPE="page"]');
	foreach($nodeCollection as $node)
	{
		if ($node->hasAttributes()) 
		{ 
			$attributes = array();
			$attrs = $node->attributes; 
			
			foreach ($attrs as $i => $attr)
			{
				$attributes[$attr->name] = $attr->value; 
			}
			
			$obj = new stdclass;
			
			if (isset($attributes['ORDER']))
			{
				$obj->order = $attributes['ORDER'];				
			}

			if (isset($attributes['LABEL']))
			{
				$obj->label = $attributes['LABEL'];	
			}	
						
			foreach($xpath->query('mets:fptr/@FILEID', $node) as $n)
			{
				$obj->bhl = $n->firstChild->nodeValue;
				$obj->bhl = preg_replace('/page(Img)?/', '', $obj->bhl);
			}
			
			// assume order is 1-offset
			if (isset($obj->order))
			{			
				$sequence = $obj->order - 1;
				
				if (isset($obj->label))
				{			
					$pages[$sequence]->pageLabel = $obj->label;
					
					// if no page numbering from scan data try and use BHL
					if (!isset($pages[$sequence]->pageNumber))
					{
						if (preg_match('/p\.\s+(?<number>\d+)/', $pages[$sequence]->pageLabel, $m))
						{
							$pages[$sequence]->pageNumber = $m['page'];
						}
					}
				}
				
				if (isset($obj->bhl))
				{			
					$pages[$sequence]->bhl = $obj->bhl;
				}								
			}			
		}
	}
	
	return $pages;
}

//----------------------------------------------------------------------------------------
function get_bhl_mets($metadata)
{
	$xml = '';
	
	$filename = '';
	foreach ($metadata->files as $file)
	{
		if ($file->format == 'Biodiversity Heritage Library METS')
		{
			$filename = $file->name;		
		}
	}
	
	if ($filename != '')
	{
		$dir = get_cache_directory($metadata->metadata->identifier);	
	
		$xml_filename = $dir. '/' . $filename;
	
		if (!file_exists($xml_filename))
		{
			$url = 'https://archive.org/download/' . clean_identifier($metadata->metadata->identifier) . '/' . rawurlencode($filename);
			$xml = get($url);	
			file_put_contents($xml_filename, $xml);
		}
	
		$xml = file_get_contents($xml_filename);
	}

	return $xml;
}

//----------------------------------------------------------------------------------------
// Crude HTML display (for debugging)
function to_html($pages, $ia)
{
	foreach ($pages as $sequence => $page)
	{
		$google_width = 685;
		
		$scale = $google_width / $page->width;
		
		$w = $google_width;
		$h = round($scale * $page->height, 0) + 1; // +1 if we have a border for our pages
		
		echo '<div id="page-' . ($sequence + 1) . '" class="page" style="width:' . $w . 'px;height:' . $h . 'px;"';
		
		$info = new stdclass;
		$info->sequence = $sequence;
		
		// do we have a proper page number?
		if (isset($page->pageNumber))
		{
			$info->number = $page->pageNumber;
		}	
		
		echo ' data-page="' . urlencode(json_encode($info)) . '"';
					
		echo '>';
				
		// https://www.rfc-editor.org/rfc/rfc3778
		// #page= in PDF is the physical page, i.e., 1,2,...,n 
		echo '<a name="page=' .  ($sequence + 1) . '"></a>';
		
		// treat page number as a label
		if (isset($page->pageNumber))
		{
			echo '<a name="' .  $page->pageNumber . '"></a>';
		}
		
		echo '<img class="lazy" data-src="http://localhost/archive-viewer-o/imageproxy.php?url=https://archive.org/download/' . clean_identifier($ia) . '/page/n' . $sequence . '_w' . $google_width . '.jpg">';
		echo '</div>';
		echo "\n";	
	}
}

//----------------------------------------------------------------------------------------
// Convert a simple CSL object to SQL so we have basic metadata available
function csl_to_sql($csl, $table = "metadata")
{
	$keys = array();
	$values = array();
	
	$guid = $csl->id; // this will always be our "guid"

	$keys[] = 'guid';
	$values[] = '"' . $guid . '"';
	
	foreach ($csl as $k => $v)
	{
		switch ($k)
		{		
			case 'DOI':
				$keys[] ='doi';
				$values[] = '"' . $v . '"';	
				break;		

			case 'URL':
				$keys[] ='url';
				$values[] = '"' . $v . '"';	
				break;		
			
			case 'type':
			case 'volume':
			case 'issue':
			case 'page':
			//case 'publisher':
			//case 'abstract':
				$keys[] = $k;
				$values[] = '"' . str_replace('"', '""', $v) . '"';	
				break;	
	
			case 'container-title':
				if (is_array($v) && count($v) > 0)
				{
					$keys[] = 'containerTitle';
					$values[] = '"' . str_replace('"', '""', $v[0]) . '"';					
				}
				else 
				{
					$keys[] = 'containerTitle';
					$values[] = '"' . str_replace('"', '""', $v) . '"';					
				}
				break;

			case 'title':
				if (is_array($v) && count($v) > 0)
				{
					$keys[] = 'title';
					$values[] = '"' . str_replace('"', '""', $v[0]) . '"';					
				}
								
				else 
				{
					$keys[] = 'title';
					$values[] = '"' . str_replace('"', '""', $v) . '"';	
				}
				break;

			case 'ISSN':
				if (is_array($v))
				{
					$keys[] = 'issn';
					$values[] = '"' . str_replace('"', '""', $v[0]) . '"';					
				}
				else 
				{
					$keys[] = 'issn';
					$values[] = '"' . str_replace('"', '""', $v) . '"';					
				}
				break;
	
			case 'issued':
				$keys[] = 'year';
				$values[] = '"' . $v->{'date-parts'}[0][0] . '"';		
				
				$date = array();
				
				if (count($v->{'date-parts'}[0]) > 0) $date[] = $v->{'date-parts'}[0][0];
				if (count($v->{'date-parts'}[0]) > 1) $date[] = str_pad($v->{'date-parts'}[0][1], 2, '0', STR_PAD_LEFT);
				if (count($v->{'date-parts'}[0]) > 2) $date[] = str_pad($v->{'date-parts'}[0][2], 2, '0', STR_PAD_LEFT);

				if (count($date) == 1)
				{
					$date[] = '00';
					$date[] = '00';
				}

				if (count($date) == 2)
				{
					$date[] = '00';
				}
								
				if (count($date) == 3)
				{
					$keys[] = 'date';
					$values[] = '"' . join('-', $date) . '"';						
				}							
				break;	
				
			case 'author':
				$authors = array();
				
				foreach ($v as $author)
				{
					if (isset($author->family))
					{
						$name = $author->family;
						if (isset($author->given))
						{
							$name = $author->given . ' ' . $name;
						}					
						$authors[] = $name;
					}
					else
					{
						if (isset($author->literal))
						{
							$authors[] = $author->literal;
						}
					}
				}
				
				if (count($authors) > 0)
				{
					$keys[] = 'authors';
					$values[] = '"' . join(';', $authors) . '"';						
				}
				break;
		
			case 'link':
				foreach ($v as $link)
				{
					if (($link->{'content-type'} == 'application/pdf') && ($pdf == ''))
					{
						$keys[] = 'pdf';
						$values[] = '"' . $link->URL . '"';		
					
						$pdf = $link->URL;	
					}
				}					
				break;
				
							
			default:
				break;
		}
	}
	
	$sql = 'REPLACE INTO ' . $table . '(' . join(',', $keys) . ') VALUES (' . join(',', $values) . ');' . "\n";

	return $sql;
}

//----------------------------------------------------------------------------------------
// Convert page array to SQL
function pages_to_sql($csl, $pages, $table = "page", $image_width = 685)
{	
	$statements = array();
		
	$guid = $csl->id; // this will always be our "guid"

	foreach ($pages as $sequence => $page)
	{
		$keys = array();
		$values = array();
		
		$keys[] = 'guid';
		$values[] = '"' . $guid . '"';

		$keys[] = 'sequence';
		$values[] = $sequence;
		
		if (isset($page->pageNumber))
		{
			$keys[] = 'pageNumber';
			$values[] = '"' . $page->pageNumber . '"';			
		}

		if (isset($page->pageLabel))
		{
			$keys[] = 'pageLabel';
			$values[] = '"' . $page->pageLabel . '"';			
		}

		if (isset($page->bhl))
		{
			$keys[] = 'bhl';
			$values[] = $page->bhl;			
		}
		
		$keys[] = 'width';
		$values[] = $page->width;
		
		$keys[] = 'height';
		$values[] = $page->height;		
		
		$keys[] = 'imageUrl';
		$values[] = '"https://archive.org/download/' . clean_identifier($csl->id) . '/page/n' . $sequence . '_w' . $image_width . '.jpg"';
		
		
		$sql = 'REPLACE INTO ' . $table . '(' . join(',', $keys) . ') VALUES (' . join(',', $values) . ');';
		$statements[] = $sql;
	
	}
	
	$sql = join("\n", $statements);
	
	return $sql;
}


//----------------------------------------------------------------------------------------
function hocr_to_text($xml, $pages)
{
	$dom = new DOMDocument;
	$dom->loadXML($xml);
	$xpath = new DOMXPath($dom);

	$xpath->registerNamespace("xhtml", "http://www.w3.org/1999/xhtml");

	$sequence = 0;
	
	foreach ($xpath->query('//xhtml:div[@class="ocr_page"]') as $ocr_page)
	{
		$areas = array();
		foreach ($xpath->query('xhtml:div[@class="ocr_carea"]', $ocr_page) as $ocr_carea)
		{		
			$paragraphs = array();		
			foreach ($xpath->query('xhtml:p[@class="ocr_par"]', $ocr_carea) as $ocr_par)
			{
				$lines = array();
			
				foreach ($xpath->query('xhtml:span[@class="ocr_line"]', $ocr_par) as $ocr_line)
				{
					$words = array();
					foreach ($xpath->query('xhtml:span[@class="ocrx_word"]', $ocr_line) as $ocrx_word)
					{
						$words[] = $ocrx_word->firstChild->textContent;
					}
				
					$lines[] = join(" ", $words);
				}
			
				//print_r($lines);
			
				$paragraph_text = join("\n", $lines);
				$paragraphs[] = $paragraph_text;
			}
		
			//print_r($paragraphs);
		
			$page_text = join("\n\n", $paragraphs);		
			$areas[] = $page_text ;
		}
		$pages[$sequence]->text = join("\n", $areas);
		$sequence++;
	}

	return $pages;
}


//----------------------------------------------------------------------------------------
// get page text, store on disk as JSON file. We expect to only use this for text mining,
// not display (?)
function get_page_text($metadata, $pages)
{
	$xml = '';
	$format = '';
	
	// Djvu XML, hOCR
	
	// Try for hOCR
	$filename = '';
	foreach ($metadata->files as $file)
	{
		if ($file->format == 'hOCR')
		{
			$filename = $file->name;
			$format = $file->format;
		}
	}
	
	// get file
	if ($filename != '')
	{
		$dir = get_cache_directory($metadata->metadata->identifier);	
	
		$xml_filename = $dir. '/' . $filename;
	
		if (!file_exists($xml_filename))
		{
			$url = 'https://archive.org/download/' . clean_identifier($metadata->metadata->identifier) . '/' . rawurlencode($filename);
			$xml = get($url);	
			file_put_contents($xml_filename, $xml);
		}
	
		$xml = file_get_contents($xml_filename);
	}


	$pages = hocr_to_text($xml, $pages);
	
	return $pages;
}


//----------------------------------------------------------------------------------------
function process($ia)
{
	$metadata = get_metadata($ia);
	

	//print_r($metadata);
	
	//exit();

	$csl = metadata_to_csl($metadata->metadata);

	// print_r($csl);

	$xml = get_scandata($metadata);
	$pages = get_pages_scandata($xml);

	//echo $xml;

	// do we have any BHL info?
	$xml = get_bhl_mets($metadata);
	
	if ($xml != '')
	{
		$pages = get_bhl_pages($xml, $pages);
	}
	
	// get the text and store for data mining
	get_page_text($metadata, $pages);

	// dump text
	print_r($pages);
	
	$dir = get_cache_directory($metadata->metadata->identifier);
	foreach ($pages as $sequence => $page)
	{
		if (isset($page->text))
		{
			$filename = $dir . '/' . $sequence . '.txt';
			file_put_contents($filename, $page->text);
		}
	}

	//to_html($pages, $ia);

	$sql = csl_to_sql($csl);

	echo $sql . "\n";

	$sql = pages_to_sql($csl, $pages, 'page');

	echo $sql . "\n";
}

//----------------------------------------------------------------------------------------

$ia = 'australianentom22entob';
$ia = 'insectakoreana372061';
$ia = 'tlsj-lepid-51-4-287';
$ia = 'pubmed-PMC3406453';
//$ia = 'american-museum-novitates-3698-001-043';
$ia = 'Australian Crickets';

//$ia = 'jstor-3753228';

//$ia = 'nzoimemoir00221964';

//$ia = 'faunedemadagasc25slat';

//$ia = 'pubmed-PMC3591763';

//$ia = 'american-museum-novitates-3815-001-014';
//$ia = 'catalogueofeaste02univrich'; // scan data in different format

//$ia = 'australianentom25entob';

$ia = 'revuesuissedezoo1174schw';


process($ia);





?>
