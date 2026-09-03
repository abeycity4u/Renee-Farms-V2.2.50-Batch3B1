<?php
$root=dirname(__DIR__);
$checks=[];
function c($ok,$label){global $checks;$checks[]=$ok;if(!$ok){fwrite(STDERR,"FAIL: $label\n");}else{echo "PASS: $label\n";}}
$fi=file_get_contents($root.'/lib/farm_intelligence.php');
$rq=file_get_contents($root.'/lib/record_quality_intelligence.php');
$rd=file_get_contents($root.'/lib/ruminant_diagnostics.php');
c(strpos($fi,"record_quality_intelligence.php")!==false,'farm intelligence loads record-quality helper');
c(strpos($rq,'record_quality_poultry_age_issues')!==false,'poultry age continuity helper exists');
c(strpos($rq,'expected_age')!==false,'age helper compares elapsed-day continuity');
c(strpos($fi,'bird-age sequence needs review')!==false,'age inconsistency produces explainable signal');
c(strpos($rq,'record_quality_poultry_unstructured_medication_notes')!==false,'quick medication-note coverage helper exists');
c(strpos($fi,'medication notes are not in structured health history')!==false,'unstructured health note coverage is visible');
c(strpos($rq,'record_quality_ruminant_weight_jumps')!==false,'rapid ruminant weight-change helper exists');
c(strpos($fi,'Recorded weight decline needs verification')!==false,'rapid ruminant decline strengthens verification wording');
c(strpos($rd,'Weight measurement needs verification')!==false,'ruminant investigation surfaces measurement verification evidence');
c(strpos($rd,'may be a real animal change or a weighing/entry inconsistency')!==false,'measurement warning does not assume biological cause');
c(strpos($fi,'The recorded entry is not changed automatically')!==false,'record-quality intelligence is read-only by design');
if(in_array(false,$checks,true)){exit(1);} echo "Batch 4F record-quality intelligence: ".count($checks)."/".count($checks)." PASS\n";
