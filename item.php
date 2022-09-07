<?php

// get object to display

$pdo = new PDO('sqlite:archive.db');

//----------------------------------------------------------------------------------------
function do_query($sql)
{
	global $pdo;
	
	$stmt = $pdo->query($sql);

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

	return $item;
}


if (0)
{
	$id = 'pubmed-PMC3591763';

	$item = get_item($id);

	print_r($item);
}


?>
