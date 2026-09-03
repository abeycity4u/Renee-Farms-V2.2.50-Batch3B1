<?php
$root=dirname(__DIR__);$fails=[];$checks=[];
$head=file_get_contents($root.'/navbar_head.php');$js=file_get_contents($root.'/assets/js/confirmations.js');$css=file_get_contents($root.'/assets/css/confirmations.css');$page=file_get_contents($root.'/management/ruminant_membership_integrity.php');
$checks['central JS globally linked']=strpos($head,"/assets/js/confirmations.js")!==false;
$checks['central CSS globally linked']=strpos($head,"/assets/css/confirmations.css")!==false;
$checks['delegated data-confirm forms supported']=strpos($js,"form[data-confirm]")!==false;
$checks['submitter preserved']=strpos($js,'submitter.name')!==false;
$checks['mobile action layout centralized']=strpos($css,'flex-direction:column-reverse')!==false;
$checks['membership repair uses central confirmation']=strpos($page,'data-confirm-title="Close membership at exit date?"')!==false && strpos($page,'onsubmit="return confirm(')===false;
$native=[];$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));foreach($it as $f){if(!$f->isFile())continue;$path=$f->getPathname();if(strpos($path,DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR)!==false)continue;if(!preg_match('/\\.(php|js)$/',$path))continue;if(basename($path)===basename(__FILE__))continue;$txt=file_get_contents($path);if(preg_match('/\\b(?:confirm|alert|prompt)\\s*\\(/',$txt))$native[]=substr($path,strlen($root)+1);}
$checks['no native confirm/alert/prompt calls remain']=count($native)===0;
foreach($checks as $n=>$ok){echo ($ok?'PASS':'FAIL')." - $n\n";if(!$ok)$fails[]=$n;}if($native)echo 'Native: '.implode(', ',$native)."\n";exit($fails?1:0);
