<?php

// Simple HTML viewer, could use as an IFRAME

require_once(dirname(__FILE__) . '/item.php');

$id = 'pubmed-PMC3591763';
$id = 'faunedemadagasc25slat';
//$id = 'nzoimemoir00221964';
$id = 'AustralianCrickets';
//$id ='american-museum-novitates-3815-001-014';
//$id ='australianentom25entob';

//$id ='revuesuissedezoo1174schw';

if (isset($_GET['id']))
{
	$id = $_GET['id'];
}

$item = get_item($id);

?>

<!DOCTYPE html>
<html>
<head>
  <script src="intersection-observer.js"></script>
  <style>
        body {
          padding:0px;
          margin:0px;
          background-color:rgb(225,225,225); /* Google Books */
        }
                 
		.page {
			background-color:white;
			width:685px; /* Google Books */
			margin: 0 auto;
			margin-bottom:1em;
			margin-top:1em; 
			padding:0px;
			border:1px solid rgb(197,197,197);
		}
		
		.page img {
			width: 100%;
			height: 100%;
		}
				
		.lazy {
			-webkit-user-select: none;
			user-select: none;
		}
     
  </style>
  <title>Viewer</title>
</head>
<body>
<?php

// Basic metadata

foreach ($item->pages as $page)
{
	echo '<div class="page" ';
	
	echo 'id="page-' . ($page->sequence + 1) . '" ';

	$google_width = 685;		
	$scale = $google_width / $page->width;
	
	$w = $google_width;
	$h = round($scale * $page->height, 0) + 1; // +1 if we have a border for our pages
		
	echo 'style="width:' . $w . 'px;height:' . $h . 'px;" ';
		
	$info = new stdclass;
	$info->sequence = (Integer)$page->sequence;
	
	// do we have a proper page number?
	if (isset($page->pageNumber))
	{
		$info->number = (Integer)$page->pageNumber;
	}	
	
	if (isset($page->pagelabel))
	{
		$info->label = $page->pagelabel;
	}	

	if (isset($page->bhl))
	{
		$info->bhl = (Integer)$page->bhl;
	}	

	if (isset($page->tags))
	{
		$info->tags = $page->tags;
	}	
	
	echo 'data-page="' . urlencode(json_encode($info)) . '" ';
	echo '>' . "\n";
	
	// https://www.rfc-editor.org/rfc/rfc3778
	// #page= in PDF is the physical page, i.e., 1,2,...,n 
	echo '<a name="page=' .  ($page->sequence + 1) . '"></a>' . "\n";
	
	// treat page number as a label
	if (isset($page->pageNumber))
	{
		echo '<a name="' .  $page->pageNumber . '"></a>' . "\n";
	}

	echo '<img class="lazy" data-src="imageproxy.php?url=' . $page->imageUrl .  '" onerror="retry(this)" >' . "\n";

	echo '</div>' . "\n";
}
?>

<script>
	window.parent.document.getElementById('title').innerHTML="<?php echo $item->title; ?>";
</script>

  <script src="lazy.js"></script>
</body>
</html>
