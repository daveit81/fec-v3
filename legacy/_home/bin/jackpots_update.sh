/usr/bin/curl -fsS --max-time 60 -G "https://www.freeextrachips.com/index.php" \
  --data-urlencode "option=com_jackpots" \
  --data-urlencode "lang=en" \
  --data-urlencode "task=data.update" >/dev/null 2>&1
