#!/bin/bash
cd /var/www/esahlan/backend/storage/app/public

find . -name "*.mp4" ! -path "*/optimized.mp4" ! -path "*/480p.mp4" ! -path "*/hls/*" | while read f; do
  DIR=$(dirname "$f")/$(basename "$f" .mp4)
  
  # Skip if already processed
  [ -f "$DIR/optimized.mp4" ] && [ -d "$DIR/hls" ] && continue
  
  mkdir -p "$DIR" "$DIR/hls"
  
  # Optimized mp4 (fallback)
  [ ! -f "$DIR/optimized.mp4" ] && ffmpeg -i "$f" -c:v libx264 -preset fast -crf 28 -c:a aac -b:a 96k -movflags +faststart -y "$DIR/optimized.mp4" 2>/dev/null
  
  # Thumbnail
  [ ! -f "$DIR/thumb.jpg" ] && ffmpeg -i "$f" -ss 00:00:01 -vframes 1 -q:v 5 -vf scale=480:-2 -y "$DIR/thumb.jpg" 2>/dev/null
  
  # HLS 480p
  [ ! -f "$DIR/hls/480p.m3u8" ] && ffmpeg -i "$f" -vf scale=854:-2 -c:v libx264 -preset fast -crf 28 -c:a aac -b:a 96k -hls_time 6 -hls_list_size 0 -hls_segment_filename "$DIR/hls/480p_%03d.ts" -y "$DIR/hls/480p.m3u8" 2>/dev/null
  
  # HLS 720p (if source >= 720p)
  W=$(ffprobe -v quiet -select_streams v:0 -show_entries stream=width -of csv=p=0 "$f")
  H=$(ffprobe -v quiet -select_streams v:0 -show_entries stream=height -of csv=p=0 "$f")
  if [ "${W:-0}" -ge 1280 ] || [ "${H:-0}" -ge 720 ]; then
    [ ! -f "$DIR/hls/720p.m3u8" ] && ffmpeg -i "$f" -vf scale=1280:-2 -c:v libx264 -preset fast -crf 26 -c:a aac -b:a 128k -hls_time 6 -hls_list_size 0 -hls_segment_filename "$DIR/hls/720p_%03d.ts" -y "$DIR/hls/720p.m3u8" 2>/dev/null
  fi
  
  # Master playlist
  if [ ! -f "$DIR/hls/master.m3u8" ]; then
    echo '#EXTM3U' > "$DIR/hls/master.m3u8"
    echo '#EXT-X-VERSION:3' >> "$DIR/hls/master.m3u8"
    [ -f "$DIR/hls/480p.m3u8" ] && echo -e '#EXT-X-STREAM-INF:BANDWIDTH=800000,RESOLUTION=854x480\n480p.m3u8' >> "$DIR/hls/master.m3u8"
    [ -f "$DIR/hls/720p.m3u8" ] && echo -e '#EXT-X-STREAM-INF:BANDWIDTH=2000000,RESOLUTION=1280x720\n720p.m3u8' >> "$DIR/hls/master.m3u8"
  fi
done

chown -R www-data:www-data /var/www/esahlan/backend/storage/app/public/
