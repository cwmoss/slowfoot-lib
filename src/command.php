<?php
$slft_lib_base = __DIR__;

$doc = <<<DOC
slowfoot.

Usage:
  slowfoot dev [-S <server:port>] [-f | --fetch <content source>]
  slowfoot build
  slowfoot (-h | --help)
  slowfoot --version

Options:
  -f                        fetch all contents
  --fetch <content source>  fetch contents from <content source>
  -h --help                 Show this screen.
  --version                 Show version.
  -S --server <server:port> Set server and port [default: localhost:1199]

DOC;

//require_once(__DIR__.'/../vendor/autoload.php');

$parsed = Docopt::handle($doc, array('version'=>'slowfoot 0.1'));
#var_dump($parsed);
$args = $parsed->args;

// https://www.kammerl.de/ascii/AsciiSignature.php rounded

$logo = '
       _                ___                 
      | |              / __)            _   
   ___| | ___  _ _ _ _| |__ ___   ___ _| |_ 
  /___) |/ _ \| | | (_   __) _ \ / _ (_   _)
 |___ | | |_| | | | | | | | |_| | |_| || |_ 
 (___/ \_)___/ \___/  |_|  \___/ \___/  \__)
                                            
 ';

if ($args['dev']) {
    print $logo."\n";

    $FETCH = $args['-f'];

    // evtl. fetching data
    require __DIR__ . '/boot.php';

    print console_table(['_type'=>'type', 'total'=>'total'], $ds->info());
    
    // this wont work :)
    // `(sleep 1 ; open http://localhost:1199/ )&`;
    // this works!
    shell_exec('(sleep 1 ; open http://localhost:1199/ ) 2>/dev/null >/dev/null &');
    $command = "PHP_CLI_SERVER_WORKERS=4 php -S localhost:1199 -t src/ {$slft_lib_base}/development.php";
    print "\n\n";

    print "starting development server\n\n";
    print "   🌈 http://localhost:1199\n\n";
    print "have fun!\n\n";
    $wss = "php {$slft_lib_base}/wss.php ".SLOWFOOT_BASE;
    #shell_exec("$wss &");
    #print "end";
    `$command`;
    #`($command &) && ($wss &)`;
}
if ($args['build']) {
    print $logo."\n";
    
    require __DIR__ . '/boot.php';
    include 'build.php';
}
