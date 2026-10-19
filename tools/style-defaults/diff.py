import json
a=json.load(open('snap-before.json')); b=json.load(open('snap-after.json'))
PROPS='color|background-color|background-image|font-size|font-weight|line-height|letter-spacing|text-transform|padding-top|padding-right|padding-bottom|padding-left|margin-top|margin-bottom|border-top-left-radius|border-top-width|box-shadow|width|height|gap|justify-content|text-align|opacity|display'.split('|')
n=0
for vp in a:
    for k,v in a[vp].items():
        w=b[vp].get(k)
        if w is None: print(vp,'missing',k); n+=1; continue
        for p,x,y in zip(PROPS,v.split('|'),w.split('|')):
            if x!=y:
                try:
                    if x.endswith('px') and y.endswith('px') and abs(float(x[:-2])-float(y[:-2]))<0.05: continue
                except ValueError: pass
                print(vp,k,p,x,'→',y); n+=1
print('differences:',n)
