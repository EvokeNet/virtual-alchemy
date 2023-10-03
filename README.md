# Virtual Alchemy Bot for Evoke

A proof of concept for providing AI-driven, formative feedback to student work using Evoke Portfolios and ChatGPT

This script is dependent on two libraries via composer:

    composer require vlucas/phpdotenv
    composer require guzzlehttp/guzzle:^7.0

This PoC may be ran from the terminal:

    php alchemybot.php

Edit variables in .var as well in alchemybot.php.

The script first queries the Evoke Portfolio for student assignment posts given the course ID and date ranges (set course ID to zero to retrieve all courses.). The returned json file is echoed to the terminal and each returned portfolio activity post is processed through the OpenAI API. Resultant text from ChatGPT is then echoed in the terminal and posted as a comment to the student work on Moodle.