#!/usr/bin/php

<?php


echo "\n~~~~~~~~~~~~~~~~~\n~~~PMC_Grabber~~~\n~~~~~~~~~~~~~~~~~\n\n";
date_default_timezone_set('America/New_York');
error_reporting(E_ALL & ~E_NOTICE);

//Create the variable that is used to name the output folder for XMLs and PDFs
echo "Enter the desired output folder for files to be saved to and press [Enter]: ";

$searchNamespace = trim(fgets(STDIN));

//Get the search terms from the user and use them to query eSearch 
echo "Enter the search terms to be used for PMC Grabber and press [Enter]: ";

$searchTerm = trim(fgets(STDIN));
$searchTermEncoded = urlencode($searchTerm);
$combinedSearch = "https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi?db=pubmed&retmode=json&retmax=1000&tool=FSU_IR&email=mmohkamkarfsu@gmail.com&term={$searchTermEncoded}";
$responseSearch = file_get_contents($combinedSearch) or die("Problem with eSearch");
$jsonResponse = json_decode($responseSearch);

$count = $jsonResponse->esearchresult->count;


// Create the ID List String to pass to eSummary (must be comma-separated with no spaces)
$idList = "";
$i = "";
for ($i = 0; $i < $count; $i++){
    $idList .= "{$jsonResponse->esearchresult->idlist[$i]}";
    if($i != ($count - 1)){
    $idList .= ",";
    }
}

//Create array from $idList
$passed = $idList;
$passed = explode(",", $passed);
$passedcount = count($passed);

//this handle is the one that opens/creates the master array with all data
$handlemaster = fopen('./csvindexmaster.csv', 'a');

//Opens csvindexmaster.csv returns the value for the first column to $uidarray
$row = 1;
$uidarray = [];
if (($handlemaster = fopen("csvindexmaster.csv", "r")) !== FALSE) {
    while (($data = fgetcsv($handlemaster, 10000, ",")) !== FALSE) {
        $row++;
            $uidarray[] = $data[0];
    }
    fclose($handlemaster);
}

$handlemaster = fopen('./csvindexmaster.csv', 'a');

$newrecords = array();
$datearray = array();
$termarray = array();

echo "The number of UIDs being checked against csvindexmaster.csv is $passedcount. \n";
//The following for statement iterates through the number of items passed to it and adds them to the master
for($i = 0; $i < $passedcount; $i++){
if(!in_array($passed[$i], $uidarray)){
	$newrecords[] = $passed[$i];
	}
}


//This count is used in several places below. It must remain below the $passed blocks to properly include them
$rowcount = count($newrecords);
echo "$rowcount new records are being added to the CSV index. \n";

//This writes the current date/time to the array subarray for date and time
$date = date('m-d-Y h:i:s A');
for($i = 0; $i < $rowcount; $i++){
$datearray[] = $date;
}

//The searchTerm is referred to earlier in this file
for($i = 0; $i < $rowcount; $i++){
$termarray[] = $searchTerm;
}

$entry = array_map(null, $newrecords, $datearray, $termarray);
foreach ($entry as $row) {
fputcsv($handlemaster, $row);
}


//The $recordscount variable opens the csv and reads the number of rows/records
$recordscount = file('./csvindexmaster.csv');
echo "There are now " . count($recordscount)." total records in the CSV index. \n";

//the $newrecords array is a list of only those UIDS that did not exist in the original CSV
if($newrecords == true){
echo "The retreived records are being checked against the CSV index. \n";
}else {
	echo "CSV index is up-to-date. No new records have been passed. \n";
}

if ($newrecords == true){
//all new records being added are changed to a comma separated list to be added
$idList = implode(",",$newrecords);

// Construct eSummary request & decode the JSON
$eSum = "https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esummary.fcgi?db=pubmed&retmode=json&id={$idList}";
$eSumResponse = file_get_contents($eSum) or die("Problem with eSummary");
$jsonESum = json_decode($eSumResponse);

// Construct eFetch request and store in XML variable (there is no JSON return from eFetch)
$eFetch = "https://eutils.ncbi.nlm.nih.gov/entrez/eutils/efetch.fcgi?db=pubmed&retmode=xml&id={$idList}";
$eFetchXML = simplexml_load_file($eFetch) or die ("Problem with loading XML from eFetch");


// The JSON array is Keyed via the UID, but the XML array is NOT, it is queued up in the order of IDs passed to it.
// If you base BOTH retrieval systems on $i and $i++, they should all maintain horizontal consistency
// Create an array from $idList and use that as the index value to sort through both datastreams at once
$idListArray = explode(",",$idList);
$recordsArray = array();




for($index = 0; $index < count($idListArray); $index++){
// VARIABLES FROM EFETCH. coming from XML stream
// MedlineCitation Vars

	// direct variables - don't need to be processed really
    $issn = $eFetchXML->PubmedArticle[$index]->MedlineCitation->Article->Journal->ISSN->__toString();

    $volume = $eFetchXML->PubmedArticle[$index]->MedlineCitation->Article->Journal->JournalIssue->Volume->__toString();

     $issue = $eFetchXML->PubmedArticle[$index]->MedlineCitation->Article->Journal->JournalIssue->Issue->__toString();

    $journalTitle = $eFetchXML->PubmedArticle[$index]->MedlineCitation->Article->Journal->Title->__toString();

    $journalAbrTitle = $eFetchXML->PubmedArticle[$index]->MedlineCitation->Article->Journal->ISOAbbreviation->__toString();

    $articleTitle = $eFetchXML->PubmedArticle[$index]->MedlineCitation->Article->ArticleTitle->__toString(); // This is a full title, inclusive of SubTitle. May have to explode out on Colon


    // array variables - returns an array, so we need to iterate and process what we want from it
    $abstract = $eFetchXML->PubmedArticle[$index]->MedlineCitation->Article->Abstract->AbstractText; // may return array to iterate for multiple paragraphs

    $authors = $eFetchXML->PubmedArticle[$index]->MedlineCitation->Article->AuthorList; // will return Array of authors.

	$grants = $eFetchXML->PubmedArticle[$index]->MedlineCitation->Article->GrantList; // returns an array with objects containing GrantID, Acronym, Agency, Country

	$keywords = $eFetchXML->PubmedArticle[$index]->MedlineCitation->KeywordList->Keyword; // returns an array which can be iterated for all keywords

   // Not all articles have it, but there are some with Mesh arrays (Medical Subject Headings)
    $mesh = $eFetchXML->PubmedArticle[$index]->MedlineCitation->MeshHeadingList; // returns array with objects for elements

// VARIABLES FROM ESUMMARY, coming from JSON stream
    $uid = $jsonESum->result->uids[$index]; // Important hook for rest of variables in JSON Tree

    // direct variables to be passed to Records Array
    $sortTitle = $jsonESum->result->$uid->sorttitle;

   $pages = $jsonESum->result->$uid->pages;

   $essnESum = $jsonESum->result->$uid->essn;

    $sortPubDate = $jsonESum->result->$uid->sortpubdate;

    // array variables, we need to iterate and process
    $articleIdESum = $jsonESum->result->$uid->articleids; // returns an array

	// PREPARE GRABBED DATA FOR PASSING TO RECORDS ARRAY
    // Abstract Parse- Whether text is contained in a single element or not, it will always return as an array.
        $abstractString = "";
        for($i = 0; $i < @count($abstract); $i++){
            $abstractString .= $abstract[$i]->__toString() ." ";
        }

   // Author Parsing - Transform original author array into new author array containing FirstName, LastName, Fullname
        $authorArray = array();
        for ($i = 0; $i < count($authors->Author); $i++){
            $fname = $authors->Author[$i]->ForeName->__toString(); // Will return a string of Firstname + Middle Initial if given
            $lname = $authors->Author[$i]->LastName->__toString();
            $fullname = $fname . " " . $lname;
            $authorArray[$i] = array("Firstname"=>$fname,"Lastname"=>$lname,"Fullname"=>$fullname);
        }  

  

   // Grant Number Parsing
  $grantIDString = "";  
if($grants == true){
        for ($i = 0; $i < count($grants->Grant); $i++){
            $grantIDString .= $grants->Grant[$i]->GrantID->__toString();
            if($i != (count($grants->Grant) - 1)){
                $grantIDString .= ", ";
            }
        } 
}
  
    // Keyword Parsing
	$keywordString = "";
	if($keywords == true){
        for ($i = 0; $i < count($keywords); $i++){
			$keywordString .= ucfirst($keywords[$i]->__toString());  // to comply with first character UC ... <3 Bryan
            if($i != (count($keywords) -1)){
                $keywordString .= ", ";
            }
	}
	} 

    // ArticleID Parsing
        $articleIdArray = array();
        for ($i = 0; $i < count($articleIdESum); $i++){
            $idtype = $articleIdESum[$i]->idtype;
            $value = $articleIdESum[$i]->value;
            // Here is where we pick out which IDs we are interested in for inclusion in MODS record
            // Any idtype not here will not be captured going forward
            if(
                    $idtype == "doi" || 
                    $idtype == "pmc" || 
                    $idtype == "mid" || 
                    $idtype == "rid" || 
                    $idtype == "eid" || 
                    $idtype == "pii" ||
                    $idtype == "pmcid"){
              $articleIdArray[$idtype] = $value;
            }
 
   
} 

            // Generate IID value from the PubMed UID
            $iid = "FSU_pmch_{$uid}";
            $articleIdArray["iid"] = $iid;
        
            // Generate PDF link & Check for Embargo & Flag Embargo Status
            if($idtype == "pmcid"){
             
                        $articleIdArray["pdf"] = "https://www.ncbi.nlm.nih.gov/pmc/articles/{$articleIdArray["pmc"]}/pdf"; // If a PDF for this PMCID exists, this link will resolve to it
             }
        
	
    // Article Title Parsing
    // Goal is to parse what we have returned into nonSort, sortTitle, startTitle, subTitle, fullTitle and store in a titleArray
        // Generate nonsort var
        $nonsorts = array("A","An","The");
        $titleArray = explode(" ", $articleTitle);
        if (in_array($titleArray[0], $nonsorts)){
            $nonsort = $titleArray[0];
            $sortTitle = implode(" ", array_slice($titleArray, 1)); // rejoins title array starting at first element
        } else {
            $nonsort = FALSE;
            $sortTitle = $articleTitle;
        } 


        // Generate subTitle and startTitle from fullTitle string
        $subTitleArray = explode(": ",$sortTitle);
            // now $subTitleArray[0] will be startTitle & [1] will be subTitle
            $startTitle = $subTitleArray[0];
            if(isset($subTitleArray[1])){
                $subTitle = $subTitleArray[1];
            }
            else{
                $subTitle = FALSE;
            }
 

   
        // Combine it all into one master title array to be parsed for MODS Record
        $parsedTitleArray = array("nonsort"=>$nonsort,"sort"=>$sortTitle,"start"=>$startTitle,"subtitle"=>$subTitle,"fulltitle"=>$articleTitle);
     
   // Parse the sortPubDate to throw away the timestamp
        // sortPubDate format is consistently: YYYY/MM/DD 00:00, and needs to become YYYY-MM-DD
        $pubDateDirty = substr($sortPubDate,0,10);
        $stringA = explode("/",$pubDateDirty);
        $pubDateClean = implode("-",$stringA);
        
   
 // Parse the page ranges for passing to MODS easily
        // Case: "217-59" needs to be understood as "217" and "259" for <start>217</start><end>259</end>
        $pagesArray = explode("-",$pages);
        if(isset($pagesArray[1])){ // Checks to make sure there was a - in the page range. If not, then an invalid page range existed and script skips this element
            if( strlen($pagesArray[0]) == 3 && strlen($pagesArray[1]) == 2  ){ // Case: 152-63, needs to be 152-163
                $append = substr($pagesArray[0],0,1);
                $pagesCorrect = $append . $pagesArray[1];
                $pages = $pagesArray[0] . "-" . $pagesCorrect;
            } else if (strlen($pagesArray[0]) == 3 && strlen($pagesArray[1]) == 1){ // Case: 152-5, needs to be 152-155
                $append = substr($pagesArray[0],0,2);
                $pagesCorrect = $append . $pagesArray[1];
                $pages = $pagesArray[0] . "-" . $pagesCorrect;
            } else if (strlen($pagesArray[0]) == 4 && strlen($pagesArray[1]) == 3){ // Case: 1555-559, needs to be 1555-1559
                $append = substr($pagesArray[0],0,1);
                $pagesCorrect = $append . $pagesArray[1];
                $pages = $pagesArray[0] . "-" . $pagesCorrect;
            } else if (strlen($pagesArray[0]) == 4 && strlen($pagesArray[1]) == 2){ // Case: 1555-59, needs to be 1555-1559
                $append = substr($pagesArray[0],0,2);
                $pagesCorrect = $append . $pagesArray[1];
                $pages = $pagesArray[0] . "-" . $pagesCorrect;
            } else if (strlen($pagesArray[0]) == 4 && strlen($pagesArray[1]) == 1){ // Case: 1555-9, needs to be 1555-1559
                $append = substr($pagesArray[0],0,3);
                $pagesCorrect = $append . $pagesArray[1];
                $pages = $pagesArray[0] . "-" . $pagesCorrect;
            }
        }
        else {
            $pages = ""; // At times the metadata for pages is simply incorrect (referring to issue or article # instead). Going to only parse page ranges if entered properly with a range, and ignore the rest
        }

  
  // Mesh Subject Terms Parsing
    // Some records will have an object array of Subject Terms for use in <subject authority="mesh"><topic></topic></subject>
    // This will parse the object-array of Mesh subject terms into Descriptor -- Qualifier for individul <topic> elements
    if($mesh){
        $meshArray = array();
        for ($i = 0; $i < count($mesh->MeshHeading);$i++){
           $meshSubArray = array();
           $descriptor = $mesh->MeshHeading[$i]->DescriptorName.""; // seems to always to be just one per
           if($mesh->MeshHeading[$i]->QualifierName){ // can be a single qualifier or a set of qualifers for the descriptor
               for($xi=0;$xi<count($mesh->MeshHeading[$i]->QualifierName); $xi++){
                   $meshSubArray[$xi] = $descriptor . "/" . $mesh->MeshHeading[$i]->QualifierName[$xi].""; 
                   $meshArray[$i] = implode("||,||",$meshSubArray);
               }
            } else {
                // Only descriptorname, so pass it on
                $meshArray[$i] = $descriptor;
            }
        }
    } else{
        $meshArray = FALSE;
    }
    

  
	// Build sub-array structures with the various metadata variables for easier processing later, structured by the MODS top level elements
        
    $titleInfoMODS = $parsedTitleArray;
    $nameMODS = $authorArray;
    $originInfoMODS = array("date"=>$pubDateClean,"journal"=>$journalTitle);
    
    if(!empty($abstractString)){
        $abstractMODS = $abstractString;
    }
    else {
        $abstractMODS = "";
    }
        
    $noteMODS = array("keywords"=>$keywordString,"grants"=>$grantIDString);
    $subjectMODS = $meshArray; // Will either be an array of subject terms, or false
    $relatedItemMODS = array("journal"=>$journalTitle,"volume"=>$volume,"issue"=>$issue,"pages"=>$pages,"issn"=>$issn,"essn"=>$essnESum);
    $identifierMODS = $articleIdArray; // See above, all process done. Renaming
        
    // Set static MODS elements
    $typeOfResourceMODS = "text";
    $genreMODS = "text";
    $languageMODS = array("text"=>"English","code"=>"eng");
    $physicalDescriptionMODS = array("computer","online resource","1 online resource","born digital","application/pdf");
    $extensionMODS = array("owningInstitution"=>"FSU","submittingInstitution"=>"FSU");
    $date = date("Y-m-d");
    $recordInfoMODS = array("dateCreated"=>$date,"descriptionStandard"=>"rda");
      
   // pass processed stuff into here and it will be stored, keyed to the UID
    $recordsArray[$uid] = array(
        "titleInfo" => $titleInfoMODS,
        "name" => $nameMODS,
        "originInfo" => $originInfoMODS,
        "abstract" => $abstractMODS,
        "note" => $noteMODS,
        "subject" => $subjectMODS,
        "relatedItem" => $relatedItemMODS,
        "identifier" => $identifierMODS,
        "recordInfo" => $recordInfoMODS);

}
} else{
	echo "There are no new records. eFetch and eSummary have not been executed. \n";
}		


//Check that $recordsArray contains data and map XML		 
if(isset($recordsArray)){
foreach($recordsArray as $modsRecord){
//GENERATE MODS RECORD FOR EACH UID REMAINING
     
$xml = new SimpleXMLElement('<mods xmlns="http://www.loc.gov/mods/v3" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:mods="http://www.loc.gov/mods/v3" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:etd="http://www.ndltd.org/standards/metadata/etdms/1.0/" xmlns:flvc="info:flvc/manifest/v1" xsi:schemaLocation="http://www.loc.gov/standards/mods/v3 http://www.loc.gov/standards/mods/v3/mods-3-4.xsd" version="3.4"></mods>');
      
      // Build Title
      
      $xml->addChild('titleInfo');
      $xml->titleInfo->addAttribute('lang','eng');
      $xml->titleInfo->addChild('title', htmlspecialchars($modsRecord['titleInfo']['start']));
      if ($modsRecord['titleInfo']['nonsort']){ $xml->titleInfo->addChild('nonSort', htmlspecialchars($modsRecord['titleInfo']['nonsort'])); }
      if ($modsRecord['titleInfo']['subtitle']){ $xml->titleInfo->addChild('subTitle', htmlspecialchars($modsRecord['titleInfo']['subtitle'])); }
      
      // Build Name
      foreach($modsRecord['name'] as $value){
          $a = $xml->addChild('name');
          $a->addAttribute('type', 'personal');
          $a->addAttribute('authority','local');
          $a->addChild('namePart',htmlspecialchars($value['Firstname']))->addAttribute('type','given');
          $a->addChild('namePart',htmlspecialchars($value['Lastname']))->addAttribute('type','family');
          $a->addChild('role');
          $r1 = $a->role->addChild('roleTerm', 'author'); 
          $r1->addAttribute('authority', 'rda');
          $r1->addAttribute('type', 'text');
          $r2 = $a->role->addChild('roleTerm', 'aut'); 
          $r2->addAttribute('authority', 'marcrelator');
          $r2->addAttribute('type', 'code');      
      }
      
      // Build originInfo
      
      $xml->addChild('originInfo');
      $xml->originInfo->addChild('dateIssued',  htmlspecialchars($modsRecord['originInfo']['date']));
      $xml->originInfo->dateIssued->addAttribute('encoding','w3cdtf');
      $xml->originInfo->dateIssued->addAttribute('keyDate','yes');
      
      // Build abstract, if field is not empty
      
      if(!empty($modsRecord['abstract'])){ 
          $xml->addChild('abstract',  htmlspecialchars($modsRecord['abstract']));
          }
         
      // Build identifiers
      
        // IID
        $xml->addChild('identifier',$modsRecord['identifier']['iid'])->addAttribute('type','IID');
      
        // DOI
        if(!empty($modsRecord['identifier']['doi'])){
            $xml->addChild('identifier',$modsRecord['identifier']['doi'])->addAttribute('type','DOI');
        }
        
        // OMC
        if(!empty($modsRecord['identifier']['pmc'])){
            $xml->addChild('identifier',$modsRecord['identifier']['pmc'])->addAttribute('type','PMCID');
        }
      
        // RID
        if(!empty($modsRecord['identifier']['rid'])){
            $xml->addChild('identifier',$modsRecord['identifier']['rid'])->addAttribute('type','RID');
        }
      
        // EID
        if(!empty($modsRecord['identifier']['eid'])){
            $xml->addChild('identifier',$modsRecord['identifier']['eid'])->addAttribute('type','EID');
        }
      
        // PII
        if(!empty($modsRecord['identifier']['pii'])){
            $xml->addChild('identifier',$modsRecord['identifier']['pii'])->addAttribute('type','PII');
        }
     
      // Build Related Item
      
        if(!empty($modsRecord['relatedItem']['journal'])){
            $xml->addChild('relatedItem')->addAttribute('type','host');
            $xml->relatedItem->addChild('titleInfo');
            $xml->relatedItem->titleInfo->addChild('title',  htmlspecialchars($modsRecord['relatedItem']['journal']));
            
            if(!empty($modsRecord['relatedItem']['issn'])){
                $xml->relatedItem->addChild('identifier',$modsRecord['relatedItem']['issn'])->addAttribute('type','issn');
            }
            
            if(!empty($modsRecord['relatedItem']['essn'])){
                $xml->relatedItem->addChild('identifier',$modsRecord['relatedItem']['essn'])->addAttribute('type','essn');
            }
            
            if(!empty($modsRecord['relatedItem']['volume']) || !empty($modsRecord['relatedItem']['issue']) || !empty($modsRecord['relatedItem']['pages'])){
                $xml->relatedItem->addChild('part');
                
                if(!empty($modsRecord['relatedItem']['volume'])){
                    $volXML = $xml->relatedItem->part->addChild('detail');
                    $volXML->addAttribute('type','volume');
                    $volXML->addChild('number',  htmlspecialchars($modsRecord['relatedItem']['volume']));
                    $volXML->addChild('caption','vol.');
                }
                
                if(!empty($modsRecord['relatedItem']['issue'])){
                    $issXML = $xml->relatedItem->part->addChild('detail');
                    $issXML->addAttribute('type','issue');
                    $issXML->addChild('number',  htmlspecialchars($modsRecord['relatedItem']['issue']));
                    $issXML->addChild('caption','iss.');
                }
                
                if(!empty($modsRecord['relatedItem']['pages'])){
                    $pagXML = $xml->relatedItem->part->addChild('extent');
                    $pagXML->addAttribute('unit','page');
                    $page_array = explode("-",$modsRecord['relatedItem']['pages']);
                    $xml->relatedItem->part->extent->addChild('start',  htmlspecialchars($page_array[0]));
                    if(isset($page_array[1])){$xml->relatedItem->part->extent->addChild('end',  htmlspecialchars($page_array[1]));}
                }
            }
        }
      
      // Build Subject
        $subjectNeedle = "||,||";
        if(!empty($modsRecord['subject'])){
            for($i=0;$i<count($modsRecord['subject']);$i++){           
                if( strpos($modsRecord['subject'][$i],$subjectNeedle) ){
                    // If true, there are multiple subject terms on one line here
                    $termsArray = explode("||,||",$modsRecord['subject'][$i]);
                    for($subIndex=0;$subIndex<count($termsArray);$subIndex++){
                        $subXML = $xml->addChild('subject');
                        $subXML->addAttribute('authority','mesh');
                        $subXML->addChild('topic',  htmlspecialchars($termsArray[$subIndex]));
                    }
                } else {
                    // If above is not true, then there is only term per line
                    $subXML = $xml->addChild('subject');
                    $subXML->addAttribute('authority','mesh');
                    $subXML->addChild('topic',  htmlspecialchars($modsRecord['subject'][$i]));
                }
            }
        }
        
      // Build Notes
        
        if(!empty($modsRecord['note']['keywords'])){
            $xml->addChild('note', htmlspecialchars($modsRecord['note']['keywords']))->addAttribute('displayLabel','Keywords');
        }
        
        if(!empty($modsRecord['note']['grants'])){
            $xml->addChild('note', htmlspecialchars($modsRecord['note']['grants']))->addAttribute('displayLabel','Grant Number');
        }
        
        $PMCLocation = @"https://www.ncbi.nlm.nih.gov/pmc/articles/{$modsRecord['identifier']['pmc']}";
        $pubNoteString = "This NIH-funded author manuscript originally appeared in PubMed Central at {$PMCLocation}.";
        
        $xml->addChild('note', $pubNoteString)->addAttribute('displayLabel','Publication Note');
      
     // Build FLVC extensions
        
        $flvc = $xml->addChild('extension')->addChild('flvc:flvc', '', 'info:flvc/manifest/v1');
        $flvc->addChild('flvc:owningInstitution', 'FSU');
        $flvc->addChild('flvc:submittingInstitution', 'FSU');
     // Add other static elements
        $xml->addChild('typeOfResource', 'text');
        $genre = $xml->addChild('genre', 'journal article');
        $genre->addAttribute('authority', 'coar');
        $genre->addAttribute('authorityURI', 'http://purl.org/coar/resource_type');
        $genre->addAttribute('valueURI', 'http://purl.org/coar/resource_type/c_6501');
        $xml->addChild('genre', 'text')->addAttribute('authority', 'rdacontent');
        $xml->addChild('language');
        $l1 = $xml->language->addChild('languageTerm', 'English');
        $l1->addAttribute('type', 'text');
        $l2 = $xml->language->addChild('languageTerm', 'eng');
        $l2->addAttribute('type', 'code');
        $l2->addAttribute('authority', 'iso639-2b');
        $xml->addChild('physicalDescription');
        $rda_media = $xml->physicalDescription->addChild('form', 'computer');
        $rda_media->addAttribute('authority', 'rdamedia'); 
        $rda_media->addAttribute('type', 'RDA media terms');
        $rda_carrier = $xml->physicalDescription->addChild('form', 'online resource');
        $rda_carrier->addAttribute('authority', 'rdacarrier'); 
        $rda_carrier->addAttribute('type', 'RDA carrier terms');
        $xml->physicalDescription->addChild('extent', '1 online resource');
        $xml->physicalDescription->addChild('digitalOrigin', 'born digital');
        $xml->physicalDescription->addChild('internetMediaType', 'application/pdf');
        $xml->addChild('recordInfo');
        $xml->recordInfo->addChild('recordCreationDate', date('Y-m-d'))->addAttribute('encoding', 'w3cdtf');
        $xml->recordInfo->addChild('descriptionStandard', 'rda');

 
// this is the directory creation
$directory = "./output/{$searchNamespace}";
$directoryUngrabbed = "./output/{$searchNamespace}/ungrabbed"; // Directory for records with full text available but no PDF
if(!is_dir($directory)){
    mkdir($directory, 0755, true);
}
$handle = "./output/{$searchNamespace}/{$modsRecord['identifier']['iid']}.xml";
$output = fopen($handle,"w");
$dom = new DOMDocument('1.0');
$dom->preserveWhiteSpace = false;
$dom->formateOutput = true;
$dom->loadXML($xml->asXML());
fwrite($output,$dom->saveXML());
fclose($output);
// GRAB PDF AND SAVE TO OUTPUT FOLDER

$PDF = file_get_contents($modsRecord['identifier']['pdf']);
if(!$PDF){
    if(!is_dir($directoryUngrabbed)){
        mkdir($directoryUngrabbed, 0755, true);
    }
    $handleUngrabbed = "./output/{$searchNamespace}/ungrabbed/{$modsRecord['identifier']['iid']}.xml";
    rename($handle,$handleUngrabbed); // Moves the XML file to the ungrabbed folder when a PDF is not grabbed
    print "Could not grab PDF for IID {$modsRecord['identifier']['iid']}\n";
} else {
    $fileNamePDF = "./output/" . $searchNamespace . "/" . $modsRecord['identifier']['iid'] . ".pdf";
    file_put_contents($fileNamePDF, $PDF);
	print "Grabbed PDF for IID {$modsRecord['identifier']['iid']}\n"; 
}
}

//Create a timestamped backup of the latest CSV
$backupCSV = "./csvindex" . time() . ".csv";
if(!copy('./csvindexmaster.csv', $backupCSV)){
	echo "CSV backup failed. \n";
}else {
	echo "CSV backup successful. \n";
}
}
