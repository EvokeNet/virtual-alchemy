<?php

//  Virtual Alchemy Bot for Evoke (proof of concept)
//  by John Moravec - jmoravec@worldbank.org
//  View README.md for instructions

// We use Guzzle to handle HTTP actions
// NOTE FOR TEAM: Distro composer is not working with new version of php well, so a new version of composer was manually installed. Use this command to install the dependencies:
// php /usr/local/bin/composer require guzzlehttp/composer

require 'vendor/autoload.php';
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

// pull credentials from dotenv
require __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Define keys, credentials, and main variables
// In the future, these should get moved to .env or passed through Moodle

$OpenAIKey = $_ENV['OPENAI_API_KEY'];
$OpenAIPath= $_ENV['OPENAI_API_PATH'];
$MoodleKey= $_ENV['MOODLE_KEY'];    // attached to user created in [moodle]/admin/webservice/tokens.php
$MoodleURI= $_ENV['MOODLE_URI'];      // no trailing slash
$MoodleAPIPath= $_ENV['MOODLE_API_PATH'];

// These should be passed dynamically in the future
$gptmodel='gpt-3.5-turbo';
$course_id='11';    // setting to 0 appears to query all courses
$start_date='2023-09-14 16:35:00';   // YYYY-MM-DD HH:MM:SS
$end_date='2024-01-11 00:00:00';    // YYYY-MM-DD HH:MM:SS

// Tune the persona of the bot
$Persona='Alchemy, a mysterious leader who is calling upon youth to solve global grand challenges. The true identity of Alchemy is unknown and he may or not be human.';    // persona to be emulated
$TargetAudience='youth "Agents"';    // description of the students

// debug on if set to 1
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

    // Pull portfolio posts
    $client = new Client(['base_uri' => $MoodleURI]);

    try {
        $response = $client->post($MoodleAPIPath, [
            'form_params' => [
                'wstoken' => $MoodleKey,
                'wsfunction' => 'mod_portfoliobuilder_alchemy_comment_get',
                'moodlewsrestformat' => 'json',
                'starttime' => $start_date,
                'endtime' => $end_date,
                'courseid' => $course_id
            ]
        ]);
    
        $data = json_decode($response->getBody()->getContents(), true);
    
        if (isset($data['exception'])) {
            throw new Exception("Moodle API Error: " . $data['message']);
        }
    
    // Continue with the rest of code to handle $data...
    } catch (RequestException $e) {
        echo "HTTP Request failed\n";
        echo $e->getRequest();
        if ($e->hasResponse()) {
            echo $e->getResponse();
        }
    } catch (Exception $e) {
        echo $e->getMessage();
    }

// comment out else echo json data to terminal for debug
print_r($data);

// run foreach loop to parse data into responses for ChatGPT
$entries = $data['entries'];
foreach ($entries as $entry) {
        $entry_id = $entry['id'];
        $entry_userid = $entry['userid'];
        $entry_title = $entry['title'];
        $entry_content = $entry['content'];
        $entry_portfolioid = $entry['portfolioid'];
        $entry_portfolioname = $entry['portfolioname'];
        $entry_portfoliodescription = $entry['portfoliodescription'];

    // Form new GPT query to expand into response       
    $client = new Client();
    $response = $client->request('POST', $OpenAIPath, [
    'headers' => [
        'Authorization' => $OpenAIKey,
        'Content-Type' => 'application/json'
    ],
    'json' => [
        'model' => $gptmodel,
                'messages' => [
            ['role' => 'system', 'content' => 'You are ' . $Persona],
            ['role' => 'assistant', 'content' => 'I will help ' . $TargetAudience . ' solve challenges by responding to portfolio posts as they work on problems.'],
            ['role' => 'user', 'content' => 'I am working on this assignment: ' . $entry_portfoliodescription],
            ['role' => 'user', 'content' => 'Provide feedback on my response to the assignment, letting me know if I have developed skills: ' . $entry_content],
            ]
            ]
        ]);

    $response_output = json_decode($response->getBody()->getContents(), true);

    // Echo results to terminal for debugging
    echo "\n\e[1;35;40mResponse: \e[0m\n\n";
    echo $response_output['choices'][0]['message']['content'];

    // Post response to portfolio in Moodle
    $response_message=nl2br($response_output['choices'][0]['message']['content']);
    $client = new Client(['base_uri' => $MoodleURI]);
    try {
        $response = $client->post($MoodleAPIPath, [
            'form_params' => [
                'wstoken' => $MoodleKey,
                'wsfunction' => 'mod_portfoliobuilder_alchemy_comment_add',
                'moodlewsrestformat' => 'json',
                'entryid' => $entry_id,
                'message' => $response_message
            ]
        ]);

        // Check response or handle other post-response actions
        $responseData = json_decode($response->getBody()->getContents(), true);
        if (isset($responseData['exception'])) {
            throw new Exception("Moodle API Error: " . $responseData['message']);
        }
    } catch (RequestException $e) {
        echo "Failed to post to Moodle: " . $e->getMessage();
    } catch (Exception $e) {
        echo $e->getMessage();
    }
}

?>