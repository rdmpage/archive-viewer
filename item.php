<?php

// get object to display

$pdo = new PDO('sqlite:archive.db');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

//----------------------------------------------------------------------------------------
function do_query($sql)
{
	global $pdo;
	
	try {	
		$stmt = $pdo->query($sql);
	} catch (PDOException $e) {
		echo 'Query failed: ' . $e->getMessage();
		exit();
	}		

	$data = array();

	while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {

		$item = new stdclass;
		
		$keys = array_keys($row);
	
		foreach ($keys as $k)
		{
			if ($row[$k] != '')
			{
				$item->{$k} = $row[$k];
			}
		}
	
		$data[] = $item;	
	}
	
	return $data;	
}

//----------------------------------------------------------------------------------------
function get_item($id)
{
	$item = null;

	// get metadata
	$sql = 'SELECT * FROM metadata WHERE guid="' . $id . '" LIMIT 1';
	
	$data = do_query($sql);
	
	if (count($data) == 1)
	{
		$item = $data[0];	
	}
	
	// get pages
	$sql = 'SELECT * FROM page WHERE guid="' . $id . '" ORDER BY sequence';
	
	$data = do_query($sql);
	
	if (count($data) > 0)
	{
		$item->pages = $data;	
	}	
	
	// get annotations
	$sql = 'SELECT * FROM annotation WHERE guid="' . $id . '" ORDER BY `sequence`';
	
	$data = do_query($sql);
	
	if (count($data) > 0)
	{
		foreach ($data as $row)
		{
			$item->pages[$row->sequence]->tags[] = $row->text;
		}
	}	


	return $item;
}


if (0)
{
	$id = 'pubmed-PMC3591763';
	$id = 'AustralianCrickets';

	$item = get_item($id);

	print_r($item);
}


?>
