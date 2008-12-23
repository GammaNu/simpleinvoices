<?php

header("Content-type: text/xml");

//global $auth_session;
//global $dbh;

$start = (isset($_POST['start'])) ? $_POST['start'] : "0" ;
$dir = (isset($_POST['sortorder'])) ? $_POST['sortorder'] : "ASC" ;
$sort = (isset($_POST['sortname'])) ? $_POST['sortname'] : "name" ;
$limit = (isset($_POST['rp'])) ? $_POST['rp'] : "25" ;
$page = (isset($_POST['page'])) ? $_POST['page'] : "1" ;

//SC: Safety checking values that will be directly subbed in
if (intval($page) != $page) {
	$start = 0;
}
$start = (($page-1) * $limit);

if (intval($limit) != $limit) {
	$limit = 25;
}
if (!preg_match('/^(asc|desc)$/iD', $dir)) {
	$dir = 'DESC';
}

$query = $_POST['query'];
$qtype = $_POST['qtype'];

$where = "  WHERE c.domain_id = :domain_id";
if ($query) $where = " WHERE c.domain_id = :domain_id AND $qtype LIKE '%$query%' ";


/*Check that the sort field is OK*/
$validFields = array('CID', 'name', 'customer_total','owing','enabled');

if (in_array($sort, $validFields)) {
	$sort = $sort;
} else {
	$sort = "CID";
}

	//$sql = "SELECT * FROM ".TB_PREFIX."customers ORDER BY $sort $dir LIMIT $start, $limit";
	$sql = "SELECT 
				c.id as CID, 
				c.name as name, 
				(SELECT (CASE  WHEN c.enabled = 0 THEN 'Disabled' ELSE 'Enabled' END )) AS enabled,
				(
					SELECT
			            coalesce(sum(ii.total),  0) AS total 
			        FROM
			            ".TB_PREFIX."invoice_items ii INNER JOIN
			            ".TB_PREFIX."invoices iv ON (iv.id = ii.invoice_id)
			        WHERE  
			            iv.customer_id  = CID ) as customer_total,
                (
                    SELECT 
                        coalesce(sum(ap.ac_amount), 0) AS amount 
                    FROM
                        ".TB_PREFIX."payment ap INNER JOIN
                        ".TB_PREFIX."invoices iv ON (iv.id = ap.ac_inv_id)
                    WHERE 
                        iv.customer_id = CID) AS paid,
                ( select customer_total - paid ) AS owing

			FROM 
				".TB_PREFIX."customers c  
			$where
			ORDER BY 
				$sort $dir 
			LIMIT 
				$start, $limit";

	$sth = dbQuery($sql, ':domain_id', $auth_session->domain_id) or die(htmlspecialchars(end($dbh->errorInfo())));
	$customers = $sth->fetchAll(PDO::FETCH_ASSOC);

$count = $sth->rowCount();

	$xml .= "<rows>";
	$xml .= "<page>$page</page>";
	$xml .= "<total>$count</total>";
	
	foreach ($customers as $row) {
		$xml .= "<row id='".$row['CID']."'>";
		$xml .= "<cell><![CDATA[
			<a class='index_table' title='$LANG[view] $LANG[customer] ".utf8_encode($row['name'])."' href='index.php?module=customers&view=details&id=$row[CID]&action=view'><img src='images/common/view.png' height='16' border='-5px' padding='-4px' valign='bottom' /></a>
			<a class='index_table' title='$LANG[edit] $LANG[customer] ".utf8_encode($row['name'])."' href='index.php?module=customers&view=details&id=$row[CID]&action=edit'><img src='images/common/edit.png' height='16' border='-5px' padding='-4px' valign='bottom' /></a>
		]]></cell>";		
		$xml .= "<cell><![CDATA[".$row['CID']."]]></cell>";		
		$xml .= "<cell><![CDATA[".utf8_encode($row['name'])."]]></cell>";
		$xml .= "<cell><![CDATA[".utf8_encode(siLocal::number($row['customer_total']))."]]></cell>";
		$xml .= "<cell><![CDATA[".utf8_encode(siLocal::number($row['owing']))."]]></cell>";
		$xml .= "<cell><![CDATA[".utf8_encode($row['enabled'])."]]></cell>";				
		$xml .= "</row>";		
	}
	$xml .= "</rows>";

echo $xml;


?> 
