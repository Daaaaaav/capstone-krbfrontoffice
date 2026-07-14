import os
import re
import glob

def clean_file(filepath):
    with open(filepath, 'r') as f:
        content = f.read()

    # Just strip out all the specific classes unconditionally
    classes = [
        r'relative pl-\[40%\] md:pl-\d+',
        r'border-b border-gray-100 md:border-b-0',
        r'before:content-\[attr\(data-label\)\]',
        r'before:absolute',
        r'before:left-\d+',
        r'before:top-\d+',
        r'md:before:top-1/2',
        r'md:before:-translate-y-1/2',
        r'before:text-\[11px\]',
        r'before:font-bold',
        r'before:uppercase',
        r'before:text-gray-500',
        r'md:before:content-none',
        r'text-right md:text-left',
        r'text-right',
    ]
    
    for c in classes:
        content = re.sub(r'\s+' + c, '', content)

    # Some cells had 'text-right' removed when they actually needed it for desktop?
    # Actually, in the screenshot, the user might complain that 'actions' column is not right-aligned anymore.
    # We will restore text-right if it's the actions column. We can do that manually later.
    
    # Also fix flex items justify
    content = re.sub(r'\s*justify-end md:justify-start', '', content)
    content = re.sub(r'\s*items-end md:items-start', '', content)

    # fix h-12 py-3 md:py-0
    content = re.sub(r'h-auto md:h-\d+\s*', 'h-12 ', content)
    content = re.sub(r'py-\d+ md:py-\d+\s*', 'py-0 ', content)

    with open(filepath, 'w') as f:
        f.write(content)
    
    print(f"Cleaned {filepath}")

for f in glob.glob('resources/views/livewire/pages/receptionist/*.blade.php'):
    clean_file(f)
