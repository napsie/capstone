<?php
declare(strict_types=1);

function classificationExportColumns(): array {
    return ['Senior Citizen ID No.','Tracking No.','Full Name','Barangay','Activity Classification',
        'Residence Classification','Effective Date','Reason','Updated By','Updated At'];
}

function classificationExportValues(array $row): array {
    return [$row['senior_id_no']?:'Not assigned',$row['tracking_no'],$row['full_name'],$row['barangay'],
        $row['activity_status'],$row['residence_status'],$row['effective_date']?:'Not reviewed',
        $row['reason']?:($row['activity_status']==='Deceased'?'From deceased registry':((int)$row['version']===0?'Automatically Active upon verification':'Awaiting Main OSCA review')),
        $row['updated_by_name']??'',$row['updated_at']??''];
}

function classificationExportMetadata(array $filters, array $summary): array {
    return [
        'Senior Classification Report',
        'Generated: '.(new DateTimeImmutable('now',new DateTimeZone('Asia/Manila')))->format('Y-m-d H:i:s').' Asia/Manila',
        'Barangay: '.$filters['barangay'].' | Activity: '.$filters['activity'].' | Residence: '.$filters['residence'],
        'Search: '.($filters['search']?:'None'),
        "Registered seniors: {$summary['total']} | Active: {$summary['active']} | Inactive: {$summary['inactive']} | Unclassified residence: {$summary['residence_unclassified']} | Deceased: {$summary['deceased']}",
        'Primary approved or deceased profiles only; linked requests do not add seniors. Archived primary profiles are excluded.',
        'Verified seniors are automatically Active; Main OSCA can update classifications. Residence means current location, not benefit eligibility or residency duration.',
    ];
}

/** Use the configured SENIORLINK logo and the same OSCA seal as records reports. */
function classificationReportLogos(?PDO $conn=null):array {
    require_once __DIR__.'/system_branding.php';
    $sources=['Logo'=>$conn?systemLogoPath($conn):dirname(__DIR__).'/images/logo.jpg',
        'OSCA'=>dirname(__DIR__).'/images/system_logos/official-osca-logo.png'];
    $logos=[];
    foreach($sources as $name=>$path){
        $bytes=@file_get_contents($path);$image=$bytes!==false?@imagecreatefromstring($bytes):false;
        if(!$image)continue;
        $scale=min(160/imagesx($image),160/imagesy($image),1);
        $w=max(1,(int)round(imagesx($image)*$scale));$h=max(1,(int)round(imagesy($image)*$scale));
        $rgb=imagecreatetruecolor($w,$h);imagefill($rgb,0,0,imagecolorallocate($rgb,255,255,255));
        imagecopyresampled($rgb,$image,0,0,0,0,$w,$h,imagesx($image),imagesy($image));
        ob_start();imagejpeg($rgb,null,90);$jpeg=(string)ob_get_clean();
        ob_start();imagepng($rgb);$png=(string)ob_get_clean();imagedestroy($rgb);imagedestroy($image);
        $logos[$name]=['width'=>$w,'height'=>$h,'jpeg'=>$jpeg,'png'=>$png];
    }
    return $logos;
}

/** Inline strings preserve leading zeros and prevent spreadsheet formula execution. */
function classificationExcel(array $rows,array $filters,array $summary,?PDO $conn=null):string {
    if(!class_exists('ZipArchive'))throw new RuntimeException('Excel export is temporarily unavailable.');
    $xml=static fn($v)=>htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u','',(string)$v)??'',ENT_XML1|ENT_QUOTES,'UTF-8');
    $sheetRows='';$rowNo=0;$merges=[];
    $columnWidths=[18,20,28,20,19,21,17,52,23,25];
    $write=function(array $values,int $style=0,float $height=20)use(&$sheetRows,&$rowNo,$xml):void{
        $rowNo++;$sheetRows.='<row r="'.$rowNo.'" ht="'.$height.'" customHeight="1">';
        foreach($values as $i=>$v)$sheetRows.='<c r="'.chr(65+$i).$rowNo.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'.$xml($v).'</t></is></c>';
        $sheetRows.='</row>';
    };
    $merged=function(string $text,int $style,float $height,string $range='A:J')use(&$rowNo,&$merges,$write):void{
        [$start,$end]=explode(':',$range);$values=array_fill(0,ord($start)-64,'');$values[count($values)-1]=$text;
        $write($values,$style,$height);$merges[]=$start.$rowNo.':'.$end.$rowNo;
    };
    $coverage=$filters['barangay']==='all'?'All Barangays':$filters['barangay'];
    $filterText='Activity: '.($filters['activity']==='all'?'All classifications':$filters['activity']).' | Residence: '.($filters['residence']==='all'?'All categories':$filters['residence']);
    $metadata=classificationExportMetadata($filters,$summary);
    $merged('REPUBLIC OF THE PHILIPPINES',2,20,'B:I');
    $merged('CITY GOVERNMENT OF PASIG',3,28,'B:I');
    $merged('OFFICE FOR SENIOR CITIZENS AFFAIRS',2,22,'B:I');
    $merged($filters['barangay']==='all'?'PASIG CITY':'BARANGAY '.mb_strtoupper($filters['barangay']),4,20,'B:I');
    $merged('SENIORLINK - Centralized Profiling and Record Management System',5,20,'B:I');
    $write([],0,10);$merged('SENIOR CLASSIFICATION REPORT',6,28);$merged($coverage,5,20);
    $write(['GENERATED DATE','','','BARANGAY COVERAGE','','','APPLIED FILTERS'],7,20);
    foreach(['A9:C9','D9:F9','G9:J9'] as $m)$merges[]=$m;
    $write([(new DateTimeImmutable('now',new DateTimeZone('Asia/Manila')))->format('F j, Y H:i'),'','',$coverage,'','',$filterText."\nSearch: ".($filters['search']?:'None')],8,40);
    foreach(['A10:C10','D10:F10','G10:J10'] as $m)$merges[]=$m;
    $write([],0,8);
    $write(['Registered seniors: '.$summary['total'],'','Active: '.$summary['active'],'','Inactive: '.$summary['inactive'],'','Unclassified residence: '.$summary['residence_unclassified'],'','Deceased: '.$summary['deceased']],9,34);
    foreach(['A12:B12','C12:D12','E12:F12','G12:H12','I12:J12'] as $m)$merges[]=$m;
    $write([],0,10);
    $headerRow=$rowNo+1;$write(classificationExportColumns(),1,32);
    foreach($rows as $index=>$row){
        $values=classificationExportValues($row);$lineCount=1;
        foreach($values as $i=>$value){$chars=$columnWidths[$i];$n=0;foreach(preg_split('/\R/u',(string)$value)?:[''] as $line)$n+=max(1,(int)ceil(mb_strlen($line)/max(1,$chars-2)));$lineCount=max($lineCount,$n);}
        $write($values,$index%2?13:12,min(409,max(30,$lineCount*14+10)));
    }
    if(!$rows)$merged('No senior profiles match these filters.',10,30);
    $write([],0,10);$merged($metadata[5],10,24);$merged($metadata[6],10,24);
    $columns='';foreach($columnWidths as $i=>$width)$columns.='<col min="'.($i+1).'" max="'.($i+1).'" width="'.$width.'" customWidth="1"/>';
    $mergeXml='';foreach($merges as $m)$mergeXml.='<mergeCell ref="'.$m.'"/>';
    $logos=classificationReportLogos($conn);
    $sheet='<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheetPr><pageSetUpPr fitToPage="1"/></sheetPr><sheetViews><sheetView showGridLines="0" zoomScale="85" workbookViewId="0"><pane ySplit="'.$headerRow.'" topLeftCell="A'.($headerRow+1).'" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols>'.$columns.'</cols><sheetData>'.$sheetRows.'</sheetData><autoFilter ref="A'.$headerRow.':J'.max($headerRow,$headerRow+count($rows)).'"/><mergeCells count="'.count($merges).'">'.$mergeXml.'</mergeCells><printOptions horizontalCentered="1"/><pageMargins left="0.3" right="0.3" top="0.4" bottom="0.4" header="0.15" footer="0.15"/><pageSetup orientation="landscape" paperSize="9" fitToWidth="1" fitToHeight="0"/><headerFooter><oddFooter>&amp;LSENIORLINK | Registered Seniors: '.$summary['total'].'&amp;RPage &amp;P of &amp;N</oddFooter></headerFooter>'.($logos?'<drawing r:id="rId1"/>':'').'</worksheet>';
    $fonts='<font><sz val="10.5"/><color rgb="FF17324D"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FF52657B"/><name val="Calibri"/></font><font><b/><sz val="18"/><color rgb="FF10365D"/><name val="Calibri"/></font><font><b/><sz val="12"/><color rgb="FF16634A"/><name val="Calibri"/></font><font><i/><sz val="10"/><color rgb="FF60758B"/><name val="Calibri"/></font><font><b/><sz val="16"/><color rgb="FF10365D"/><name val="Calibri"/></font><font><b/><sz val="10"/><color rgb="FF526F91"/><name val="Calibri"/></font>';
    $xf=static fn($font,$fill,$align='left',$border=0,$vertical='center',$indent=0)=>'<xf numFmtId="49" fontId="'.$font.'" fillId="'.$fill.'" borderId="'.$border.'" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="'.$align.'" vertical="'.$vertical.'" wrapText="1" indent="'.$indent.'"/></xf>';
    $styles='<?xml version="1.0"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="8">'.$fonts.'</fonts><fills count="5"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF174E7F"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFF2F7FC"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFE8F0F9"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="3"><border/><border><left/><right/><top/><bottom style="thin"><color rgb="FFE2EAF3"/></bottom></border><border><left/><right/><top/><bottom style="thin"><color rgb="FF174E7F"/></bottom></border></borders><cellStyleXfs count="1"><xf/></cellStyleXfs><cellXfs count="14">'
        .$xf(0,0).$xf(1,2,'left',0,'center',1).$xf(2,0,'center').$xf(3,0,'center').$xf(4,0,'center').$xf(5,0,'center').$xf(6,0).$xf(7,3,'left',0,'center',1).$xf(0,3,'left',0,'center',1).$xf(7,4,'center').$xf(5,0).$xf(0,3).$xf(0,0,'left',1,'top',1).$xf(0,3,'left',1,'top',1)
        .'</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    $drawingTypes=$logos?'<Default Extension="png" ContentType="image/png"/><Override PartName="/xl/drawings/drawing1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>':'';
    $files=[
        '[Content_Types].xml'=>'<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'.$drawingTypes.'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
        '_rels/.rels'=>'<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
        'xl/workbook.xml'=>'<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Senior Classifications" sheetId="1" r:id="rId1"/></sheets><definedNames><definedName name="_xlnm.Print_Titles" localSheetId="0">&apos;Senior Classifications&apos;!$1:$'.$headerRow.'</definedName><definedName name="_xlnm.Print_Area" localSheetId="0">&apos;Senior Classifications&apos;!$A$1:$J$'.$rowNo.'</definedName></definedNames></workbook>',
        'xl/_rels/workbook.xml.rels'=>'<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
        'xl/styles.xml'=>$styles,'xl/worksheets/sheet1.xml'=>$sheet,
    ];
    if($logos){
        $files['xl/worksheets/_rels/sheet1.xml.rels']='<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing1.xml"/></Relationships>';
        $drawing='';$rels='';$i=0;
        foreach($logos as $name=>$logo){$i++;$scale=min(85/$logo['width'],85/$logo['height']);$w=(int)round($logo['width']*$scale*9525);$h=(int)round($logo['height']*$scale*9525);$col=$name==='Logo'?0:9;$offset=(int)round(max(8,($columnWidths[$col]*7+5-85)/2)*9525);
            $drawing.='<xdr:oneCellAnchor><xdr:from><xdr:col>'.$col.'</xdr:col><xdr:colOff>'.$offset.'</xdr:colOff><xdr:row>0</xdr:row><xdr:rowOff>95250</xdr:rowOff></xdr:from><xdr:ext cx="'.$w.'" cy="'.$h.'"/><xdr:pic><xdr:nvPicPr><xdr:cNvPr id="'.$i.'" name="'.$name.' Logo"/><xdr:cNvPicPr/></xdr:nvPicPr><xdr:blipFill><a:blip r:embed="rId'.$i.'"/><a:stretch><a:fillRect/></a:stretch></xdr:blipFill><xdr:spPr><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></xdr:spPr></xdr:pic><xdr:clientData/></xdr:oneCellAnchor>';
            $rels.='<Relationship Id="rId'.$i.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/'.$name.'.png"/>';$files['xl/media/'.$name.'.png']=$logo['png'];
        }
        $files['xl/drawings/drawing1.xml']='<?xml version="1.0"?><xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'.$drawing.'</xdr:wsDr>';
        $files['xl/drawings/_rels/drawing1.xml.rels']='<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$rels.'</Relationships>';
    }
    $path=tempnam(sys_get_temp_dir(),'senior-classifications-');if($path===false)throw new RuntimeException('Unable to prepare the Excel report.');
    try{$zip=new ZipArchive();if($zip->open($path,ZipArchive::OVERWRITE)!==true)throw new RuntimeException('Unable to prepare the Excel report.');foreach($files as $name=>$contents)$zip->addFromString($name,$contents);if(!$zip->close())throw new RuntimeException('Unable to complete the Excel report.');$contents=file_get_contents($path);if($contents===false)throw new RuntimeException('Unable to read the Excel report.');return $contents;}finally{if(is_file($path))unlink($path);}
}

/** Shared records-report branding with wrapped, paginated classification details. */
function classificationPdf(array $rows,array $filters,array $summary,?PDO $conn=null):string {
    require_once __DIR__.'/pdf_report_header.php';
    $plain=static function($v):string{$t=iconv('UTF-8','Windows-1252//TRANSLIT//IGNORE',(string)$v);return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/','',$t===false?'':$t)??'';};
    $escape=static fn($v)=>str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$plain($v));
    $text=static fn($x,$y,$v,$size=7,$font='F1')=>'BT /'.$font.' '.$size.' Tf '.$x.' '.$y.' Td ('.$escape($v).') Tj ET';
    $upper=array_combine(range('A','Z'),[667,667,722,722,667,611,778,722,278,500,667,556,833,722,778,667,778,722,667,611,722,667,944,667,667,611]);
    $lower=array_combine(range('a','z'),[556,556,500,556,556,278,556,556,222,222,500,222,833,556,556,556,556,333,500,278,556,500,722,500,500,500]);
    $measure=static function(string $v,float $size=7)use($upper,$lower):float{$units=0;foreach(mb_str_split($v) as $c)$units+=$upper[$c]??$lower[$c]??(ctype_digit($c)?556:match($c){' '=>278,'-'=>333,'.',',',':',';'=>278,'/'=>278,'(',')'=>333,default=>600});return $units*$size/1000;};
    $wrap=static function(string $v,float $width,float $size=7)use($measure):array{$lines=[];foreach(preg_split('/\\R/u',$v)?:[''] as $p){while($measure($p,$size)>$width){$cut=1;while($cut<mb_strlen($p)&&$measure(mb_substr($p,0,$cut+1),$size)<=$width)$cut++;$space=mb_strrpos(mb_substr($p,0,$cut),' ');if($space!==false&&$space>0)$cut=$space;$lines[]=mb_substr($p,0,$cut);$p=ltrim(mb_substr($p,$cut));}$lines[]=$p;}return $lines;};
    $widths=[65,70,108,65,65,73,61,133,65,65];$headers=['Senior ID','Tracking No.','Full Name','Barangay','Activity','Residence','Effective Date','Reason','Updated By','Updated At'];
    $logos=classificationReportLogos($conn);$streams=[];$commands=[];$y=0;$pageNumber=0;
    $coverage=$filters['barangay']==='all'?'All Barangays':$filters['barangay'];
    $filterText='Activity: '.($filters['activity']==='all'?'All classifications':$filters['activity']).' | Residence: '.($filters['residence']==='all'?'All categories':$filters['residence']);
    $newPage=function()use(&$streams,&$commands,&$y,&$pageNumber,$filters,$summary,$headers,$widths,$logos,$coverage,$filterText,$text,$escape,$wrap){
        if($commands)$streams[]=implode("\n",$commands);$pageNumber++;
        $report=['title'=>'SENIOR CLASSIFICATION REPORT','subtitle'=>$coverage,'generated'=>(new DateTimeImmutable('now',new DateTimeZone('Asia/Manila')))->format('F j, Y H:i'),'coverage'=>$coverage,'filters'=>$filterText,'has_osca_logo'=>isset($logos['OSCA'])];
        foreach(['Logo'=>'logo','OSCA'=>'osca_logo'] as $name=>$key)if(isset($logos[$name])){$scale=min(64/$logos[$name]['width'],64/$logos[$name]['height']);$report[$key.'_width']=$logos[$name]['width']*$scale;$report[$key.'_height']=$logos[$name]['height']*$scale;}
        // Wrap the header filter text in its own metadata box, rather than truncate it.
        $report['filters']=mb_strlen($filterText)>45?'See filter details below':$filterText;
        $commands=standardPdfReportHeaderCommands($report,$escape,static fn($v,$n)=>$v,isset($logos['Logo']));
        $commands[]='0.12 0.22 0.34 rg';
        $meta=classificationExportMetadata($filters,$summary);
        $my=380;
        foreach([$filterText.' | Search: '.($filters['search']?:'None'),$meta[4]] as $line)foreach($wrap($line,770) as $part){$commands[]=$text(36,$my,$part,7);$my-=10;}
        $y=$my-5;$commands[]='0.08 0.28 0.50 rg 36 '.($y-20).' 770 24 re f';$commands[]='1 1 1 rg';$x=36;
        foreach($headers as $i=>$label){foreach($wrap($label,$widths[$i]-8,7.5) as $j=>$line)$commands[]=$text($x+4,$y-5-$j*8,$line,7,'F2');$x+=$widths[$i];}
        $commands[]='0.34 0.42 0.51 rg';$commands[]=$text(36,31,'Primary approved/deceased profiles; linked requests do not add seniors. Archived profiles excluded.',6.5,'F3');$commands[]=$text(36,21,'Activity: automatically Active upon verification; Main OSCA can update. Residence: current location; not benefit eligibility or residency duration.',6.5,'F3');
        $y-=34;
    };
    $newPage();if(!$rows){$commands[]='0.1 0.18 0.28 rg';$commands[]=$text(40,$y,'No senior profiles match these filters.',8);}
    foreach($rows as $rowIndex=>$row){$lines=[];$lineCount=1;foreach(classificationExportValues($row) as $i=>$value){$lines[$i]=$wrap((string)$value,$widths[$i]-8);$lineCount=max($lineCount,count($lines[$i]));}
        $offset=0;while($offset<$lineCount){
            if($y-23<50)$newPage();$capacity=max(1,(int)floor(($y-58)/9));$count=min($lineCount-$offset,$capacity);$height=$count*9+12;
            if($rowIndex%2===1)$commands[]='0.95 0.97 0.99 rg 36 '.($y-$height+4).' 770 '.$height.' re f';
            $commands[]='0.1 0.18 0.28 rg';$x=36;
            foreach($lines as $i=>$cell){$visible=array_slice($cell,$offset,$count);if($offset>0&&$i<3&&!$visible)$visible=$i===2?[$cell[0].' (continued)']:[$cell[0]];foreach($visible as $j=>$line)$commands[]=$text($x+4,$y-4-$j*9,$line);$x+=$widths[$i];}
            $y-=$height;$commands[]='0.83 0.88 0.93 RG 0.4 w 36 '.($y+4).' m 806 '.($y+4).' l S';$offset+=$count;
        }
    }
    $streams[]=implode("\n",$commands);$objects=[1=>'<< /Type /Catalog /Pages 2 0 R >>',3=>'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',4=>'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',5=>'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Oblique /Encoding /WinAnsiEncoding >>'];$next=6;$resources=[];
    foreach($logos as $name=>$logo){$id=$next++;$objects[$id]='<< /Type /XObject /Subtype /Image /Width '.$logo['width'].' /Height '.$logo['height'].' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length '.strlen($logo['jpeg']).">>\nstream\n".$logo['jpeg']."\nendstream";$resources[]='/'.$name.' '.$id.' 0 R';}
    $kids=[];$pageBase=$next;
    foreach($streams as $i=>$stream){$page=$pageBase+$i*2;$content=$page+1;$kids[]=$page.' 0 R';$stream.="\n0.34 0.42 0.51 rg ".$text(700,21,'Page '.($i+1).' of '.count($streams),7,'F3');$objects[$page]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 3 0 R /F2 4 0 R /F3 5 0 R >> /XObject << '.implode(' ',$resources).' >> >> /Contents '.$content.' 0 R >>';$objects[$content]='<< /Length '.strlen($stream).">>\nstream\n".$stream."\nendstream";}
    $objects[2]='<< /Type /Pages /Kids ['.implode(' ',$kids).'] /Count '.count($streams).' >>';ksort($objects);$pdf="%PDF-1.4\n";$offsets=[0];foreach($objects as $id=>$obj){$offsets[$id]=strlen($pdf);$pdf.=$id." 0 obj\n".$obj."\nendobj\n";}$start=strlen($pdf);$pdf.="xref\n0 ".(count($objects)+1)."\n0000000000 65535 f \n";for($i=1;$i<=count($objects);$i++)$pdf.=sprintf("%010d 00000 n \n",$offsets[$i]);return $pdf."trailer\n<< /Size ".(count($objects)+1)." /Root 1 0 R >>\nstartxref\n".$start."\n%%EOF";
}
