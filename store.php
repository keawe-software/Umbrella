<?php 
	include('pdf.php');
	
	if ($path = param('path')){
	
		$pdf = new PDF($invoice);
		$pdf->generate();	
		$pdf->store($path);
	} else {		
		redirect(getUrl('files','select_dir?target='.urlencode(location())));
	}
?>