# pmc_grabber

PMC_Grabber version 3 is an update to the PHP-based utility used with the NIH PubMed API interfaces. It pulls metadata from the eSummary and eFetch APIs and converts the metadata into valid MODS records.

## Setting up PMC_Grabber

1. Ensure that [git](https://git-scm.com/downloads) is installed on your computer.
2. This version of PMC_Grabber was developed and tested in a Vagrant VM. To set up Vagrant:
  * First download the latest version of [VirtualBox](https://www.virtualbox.org/wiki/Downloads).
  * Install VirtualBox.
  * Next download the latest version of [Vagrant](https://www.vagrantup.com/downloads.html).
  * Install Vagrant.
3. In the terminal, navigate to the location that you would like to create the virtual machine on your local machine and `git clone https://github.com/fsulib/fsu_ir_manager_env`.
  * Navigate into the cloned fsu_ir_manager_env directory and run `vagrant up` (the first time this is run can take several minutes).
  * After the previous step is completed type `vagrant ssh` into the terminal to enter the virtual machine.
4. Now, in the virtual machine use `cd /vagrant` to navigate to the synced folder (this folder is shared between the virtual machine and the host machine).  
  * In the terminal type `git clone https://github.com/fsulib/pmc_grabber'.
  * Now the PMC_Grabber files will appear in the synced folder in both the virtual machine and the host machine.

## Using PMC_Grabber

1. The major update to version 3 is the removal of SQL databases used in previous versions. The current version utilizes a local CSV file to store records.
  * To run PMC_Grabber in the Vagrant virtual machine, run `vagrant up` and `vagrant ssh` from the containing directory.
  * Navigate to the PMC_Grabber's containing folder.
  * Run PMC_Grabber with `php index.php`.
  
  * The first prompt will request an output folder name for XML and PDF files for the current search.
  * The second prompt will request a search term. To construct a search term, you can use the [Advance Search tool on PubMed](http://www.ncbi.nlm.nih.gov/pubmed/advanced) to build a complex string of searches.
  * The information received in the command line will include:
    * How many records were retreived from the eSearch query
    * How many of those records are new (were not already in the CSV index)
    * How many total records are in the CSV index
  
  * Review the overview below to get an understanding of how to re-tool PMC_Grabber for use at your institution. You will want to change static elements in the MODS record at the very least. Becomming familiar with the structure of PubMed's data output through eSummary and eFetch is highly recommended.

4. Review the MODS records and ingest ~~PDFs~~ into your repository.

5. The script is built to be run multiple times over a period of time.  You can run the script at any point again in the future; you should coordinate any subsequent runs with the embargo table in PHPLiteAdmin to ensure the capture and processing of new MODS records.
  * Aside from picking up expired embargo dates, the script will also pick up any new articles added to the PubMed database satisfying the original search criteria. Most new articles will have some embargo on them, usually for a year.

## Overview of Script Process

1. Initial steps
  * date_default_timezone_set is used to set the server's timezone for creation of date values. The timezone is stored for each query in the CSV index.

1. eSearch API Call
  * The first API call is to eSearch. The $combined_search variable contains an HTML-encoded string representing the search you wish to conduct.
  * eSearch returns only a list of IDs that is used in subsequent API calls for metadata on a per-record basis.
  * **Note that you can construct multiple different searches across different fields, combine them into one search string, and then pass only one API call for a complex results list.** When using this script, please keep in mind that the fewer times the API is called, the better the load handled by NIH's server.
  * Using PubMed's own Advanced Search tool is helpful in creating long, complex search strings. You can use a free HTML encoding tool from there to generate a valid, html-encoded complex search string.
  * It is helpful to note that if the same record ID would be returned multiple times from a complex string, the API will only return that ID once. Thus, you do not have to worry about duplicate IDs being fed into the subsequent API calls.
 
3. Local Database Query
  * This script initializes a CSV index called 'csvmasterindex.csv' in the directory from which 'index.php' is being run. The index stores the results of the eSearch API Call in the following three columns: PMCID, Date of Search, and Search Terms.

4. eSummary & eFetch API calls
  * Once the ID list has been filtered to contain only the IDs that have not been processed the script passes the IDs to the eSearch and eFetch APIs.
  * **Note that these APIs support comma-separated strings of IDs. Using this method will reduce 200 separate API calls to ONE, drastically limiting the strain on the server.  Please program responsibly to ensure you are not putting undue strain on the PubMed servers!**
  * PubMed notes that if more than about 200 UIDs are provided at once, the request should be made using the HTTP POST method.  This script has not been tested on a set of records larger than 200 yet.
  * For our purposes, calling both eSummary and eFetch was necessary to get at all of the relevant metadata we wanted to use in creating a MODS record.  To get a feel for which API returns what information, you should pick an ID and invoke the two APIs in two separate tabs on your browser. Keep in mind that eSummary will return JSON or XML (set through the retmode parameter), but eFetch will not return JSON and only XML (along with plain text). Thankfully, PHP can parse JSON and XML data structures with relative ease.

5. Raw Metadata Collection
  * The eSummary and eFetch API calls will return JSON and XML data structures. The JSON data is organized by UID, while the XML data (once parsed by SimpeXML in PHP) is organized in the order the IDs were passed to it.  Using a for loop with an incrementing index value starting at "0", the script can store data from both API calls for each record and ensure horizontal consistency (that is, the records will not be mixed up).
  * During this process, data from each record is stored in loop variables and at the end of each loop passed into an array. Thus, at the end of the loop process, the script is left with an array of records, each containing an array of data.
  * Not all variables were needed for our use, so to get the full benefit of accessing this data, you should review raw data outputs from the API to see what data you actually want.
  * As of now, the script stores the following pieces of metadata from the eFetch API:
    * ISSN of journal
    * Volume of journal
    * Issue of journal
    * Title of journal
    * Abbreviated title of journal
    * Title of article
    * Abstract text for article
    * Authors for article (which includes First Name + Middle Initial, Last Name, and Affiliation (but see note on Affiliation below)
    * Grant numbers associated with article
    * Keywords associated with article
    * Identifiers associated with article (doi, pmid, etc)
    * Mesh subject heading Descriptors and Qualifiers associated with article
    * We found the following available variables from eFetch not useful for our purposes:
       1. Publication Type (e.g., "JOURNAL ARTICLE")
       2. Article identifiers (the JSON structure had more information and is relatively easier to access)
  * As of now, the script stores the following pieces of metadata from the eSummary API:
    * UID of article (a PubMed-created unique ID)
    * Page range of article
    * ESSN of journal
    * Publication Date of article
    * The following pieces of metadata are available from eSummary, but also duplicative from eFetch or were not useful for our purposes:
       1. Volume of journal
       2. Issue of journal
       3. Language (was always english for our set)
       4. ISSN of journal
       5. Publication type
       6. View count (interesting to be able to grab, but we could not use it in our repository)
  * The above list of metadata available from PubMed is **not exhaustive**, however it contains the relevant data needed to form a valid MODS record.

6. Parse Raw Data
  * The raw data collected from PubMed is a collection of data strings and arrays, some of which needs to be parsed in order to be validly passed into a MODS record.
  * The following data required parsing:
     1. Abstract - Raw must be combined from paragraph arrays into a single string.
     2. Authors & Affiliation - Raw data needs to be understood. "First name" for PubMed is actually "First name + Middle Initial", which does translate nicely to MODS "given" format. However, the Affiliation string is not tightly controlled by PubMed and the contents of each author's affiliation string varies from nothing to including department names, addresses, and e-mails.  We abandoned the attempt to programmatically parse Affiliation string because there is no pattern of what to expect and it is always better to err on the side of creating a slightly incomplete, but valid MODS record instead of creating a complete, error prone record.
     3. Grant Numbers - Raw array is combined to create a comma-separated string.
     4. Keywords - Raw array is combined to create a comma-separated string.
     5. Article IDs - There are more IDs associated to an article than necessary for inclusion in a repository. In this step, we select the IDs we care about and store in the array, as well as create institutional ids (IID) for use in our repository system. This step also checks the article for embargo status and sets relevant variables depending on that check.
     6. Article Title - Raw required parsing to more easily generate MODS compliant "title" fields, checking for Non Sort and SubTitle and storing relevant pieces of the title in variables for easy translation to MODS
     7. Publication Date - PubMed does not store the date in W3CDTF form, so it must be parsed for it
     8. Pages - MODS requires a <start> and <end> value, which presents a problem for raw page ranges such as "235-45". I wrote a script to detect this form and fix abbreviated page ranges.
     9. Mesh Subject Terms - The raw data here is tricky to parse properly, especially since the MeSH subject strings do not really match the MODS <subject> hierarchy. We decided to combine Descriptor/Qualifier pairs into a single string for each pair. We plan to update this in the future to also check against the MeSH authority DTD file to produce a valueURI for the MODS record.

7. Store Parsed Data into Records Array
  * Once the raw data is parsed for each article, the data is passed to an array that stores all data for all records. The script uses this array to populate the MODS record for each UID.

8. Populate Local Database with Embargoed or Protected IDs
  * At this point, the script is left with an array of records that are flagged as embargoed or protected, so the script populates the local database with these IDs and purges these IDs from the remaining ID Array, leaving only records that are valid to process into MODS.

9. Generate MODS Record
  * The next step is to dynamically create a MODS record for each record stored in the Records Array. Not all records will have the same metadata available, so empty checks are used in order to produce a valid MODS record for each article.
  * Every time a MODS record is generated for an ID, that ID is then stored in the "processed" table in SQLite, so when script is run again the ID will not be processed.
  * Note that at the end of the MODS Record Generation portion of this script, a number of static MODS elements are included. This was created for our institution's circumstances, so you should review and make sure to change any information not relevant for your repository.

10. Writing Files
  * The last step is for the script to write the MODS file to the /output/ folder using iid.xml as a naming convention
    



