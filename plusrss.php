<?
/****************************************
 * SET YOUR KEY
 ****************************************/
  // Get your key at https://code.google.com/apis/console
  $key = getenv('PLUS_KEY');
  // $key = 'insert-your-plus-key-here-or-in-shell-environment-as-above'

/****************************************
 * PARAMETERS
 ****************************************/
  $uid = "106413090159067280619"; // ID of Plus user - the long number in their profile URL
  $size = 20; // number of RSS items
  $cachetime = 5 * 60;
  $cachefile = "/tmp/index-cached-".md5($_SERVER["REQUEST_URI"]).".html";
  date_default_timezone_set('gmt');

/****************************************
 * SERVE FROM CACHE IF EXISTS
 ****************************************/
  // http://simonwillison.net/2003/may/5/cachingwithphp/ modded
  if (file_exists($cachefile) && time() - $cachetime < filemtime($cachefile)) {
    print file_get_contents($cachefile);
    exit;
  }
  ob_start();

/****************************************
 * GO FETCH
 ****************************************/
  $url = "https://www.googleapis.com/plus/v1/people/$uid/activities/public?key=$key&maxResults=$size";
  $activities = json_decode(file_get_contents($url));
  $items = $activities -> items;

/****************************************
 * HELPERS TO PROCESS SOME OF THE DATA
 ****************************************/

  function pubDate($item) { return gmdate(DATE_RFC822, strtotime($item -> published)); }

  function content($item) {

    $object = $item -> object;

    if ($item->verb == 'share') {
      $source = "<a href={$object->actor->url}>{$object->actor->displayName}</a>";
      $content .= "{$item->annotation}<p>&nbsp;<br/><em>$source:</em></p><blockquote>{$object->content}</blockquote>";
    } else {
      $content .= $object -> content;
    }

    if ($object->attachments and sizeof($object->attachments)) {
      $attachment = $object->attachments[0];
      if ($attachment->objectType == 'photo')
        $content.="<p><a href='{$attachment->url}'><img width='{$attachment->image->width}' ".
                  "height='{$attachment->image->height}' src='{$attachment->image->url}' /></a></p>";
      else if ($attachment->objectType='article')
        $content .= "<p><a href='{$attachment->url}'>{$attachment->displayName}</a></p>";
    }

    return utf8_encode(htmlspecialchars($content));

  }

/****************************************
 * PUMP OUT THE FEED
 ****************************************/
?>
<? echo '<?xml version="1.0" encoding="UTF-8"?>'."\n" ?>
<rss xmlns:dc="http://purl.org/dc/elements/1.1/" version="2.0">
  <channel>
    <title><?= $activities -> title ?></title>
    <link>http://plus.google.com/<?= $uid ?>/posts</link>
    <pubDate><?= sizeof($items) ? pubDate($items[0]) : ""?></pubDate>
    <dc:date><?= sizeof($items) ? $items[0]->published : ""?></dc:date>
<? foreach ($items as $item) {
   $item_content = content($item);
 ?>
    <item>
      <title><?= $item -> title ?>...</title>
      <link><?= $item -> url ?></link>
      <description><?= $item_content ?></description>
      <pubDate><?= pubDate($item) ?></pubDate>
      <guid><?= $item -> url ?></guid>
      <dc:date><?= $item -> published ?></dc:date>
    </item>
<? } ?>
  </channel>
</rss><?
/****************************************
 * WRITE ALL THAT TO CACHE
 ****************************************/
  $fp = fopen($cachefile, 'w');
  fwrite($fp, ob_get_contents());
  fclose($fp);
  ob_end_flush(); // Send the output to the browser
?>
