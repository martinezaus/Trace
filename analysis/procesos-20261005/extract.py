import sys, subprocess
from pathlib import Path
sys.path.insert(0, '.codex-tools/video')
import imageio_ffmpeg
ff=imageio_ffmpeg.get_ffmpeg_exe()
for n,name in enumerate(['14.46.23','14.50.40'],1):
 p=Path('C:/Users/Pablo/Downloads')/f'WhatsApp Video 2026-10-05 at {name}.mp4'
 r=subprocess.run([ff,'-hide_banner','-i',str(p)],capture_output=True,text=True)
 print(name,r.stderr[:2200])
 subprocess.run([ff,'-hide_banner','-loglevel','error','-i',str(p),'-vf','fps=1/8,scale=640:-1','-y',f'analysis/procesos-20261005/video{n}-%03d.jpg'],check=True)
from PIL import Image,ImageDraw
for n in [1,2]:
 files=list(Path('analysis/procesos-20261005').glob(f'video{n}-*.jpg'))
 thumbs=[]
 for i,p in enumerate(files):
  im=Image.open(p);im.thumbnail((400,310));thumbs.append((im,i*8))
 sheet=Image.new('RGB',(400*3,340*((len(thumbs)+2)//3)),'white');d=ImageDraw.Draw(sheet)
 for i,(im,t) in enumerate(thumbs):
  x=(i%3)*400;y=(i//3)*340;sheet.paste(im,(x,y+22));d.text((x+5,y+3),f'Video {n} ~{t}s',fill='black')
 sheet.save(f'analysis/procesos-20261005/contact{n}.jpg')
