<?php

error_reporting(E_ALL);

require_once(dirname(__FILE__) . '/textsearch.php');

$config['cache']   = dirname(__FILE__) . '/cache';

$headings = array();

$row_count = 0;

$filename = "names.tsv";

$file_handle = fopen($filename, "r");
while (!feof($file_handle)) 
{
	$line = trim(fgets($file_handle));
		
	$row = explode("\t",$line);
	
	$go = is_array($row) && count($row) > 1;
	
	if ($go)
	{
		if ($row_count == 0)
		{
			$headings = $row;		
		}
		else
		{
			$obj = new stdclass;
		
			foreach ($row as $k => $v)
			{
				if ($v != '')
				{
					$obj->{$headings[$k]} = $v;
				}
			}
		
			// print_r($obj);	
			
			$filename = $config['cache'] . '/' . $obj->guid . '/' . $obj->sequence . '.txt';
			
			if (file_exists($filename))
			{
				$text = file_get_contents($filename);
				
				//echo $text;
				
				$result = find_in_text($obj->name, $text, true);
	
				// print_r($result);
				
				if ($result->total != 0)
				{
					foreach ($result->selector as $selector)
					{
						$keys = array();
						$values = array();
		
						$keys[] = 'guid';
						$values[] = '"' . $obj->guid . '"';
						
						if (isset($obj->sequence))
						{
							$keys[] = 'sequence';
							$values[] = $obj->sequence;						
						}

						if (isset($obj->page))
						{
							$keys[] = 'page';
							$values[] = '"' . $obj->page . '"';						
						}
						

						$keys[] = 'body';
						$values[] = '"' . $selector->body . '"';						
						
						$keys[] = 'exact';
						$values[] = '"' . $selector->exact . '"';						

						$keys[] = 'prefix';
						$values[] = '"' . $selector->prefix . '"';						

						$keys[] = 'suffix';
						$values[] = '"' . $selector->suffix . '"';						

						$keys[] = 'start';
						$values[] = $selector->range[0];						

						$keys[] = 'end';
						$values[] = $selector->range[1];	
											
						$keys[] = 'score';
						$values[] = $selector->score;	
						
						$sql = 'REPLACE INTO annotation (' . join(',', $keys) . ') VALUES (' . join(',', $values) . ');';
						
						echo $sql . "\n";
					}
				}			
			}
		}
	}	
	$row_count++;	
	
}	

?>
