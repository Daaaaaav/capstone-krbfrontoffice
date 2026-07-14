import os
import re
import glob

def clean_file(filepath):
    with open(filepath, 'r') as f:
        content = f.read()

    # Clean up the exact classes regardless of variations
    classes_to_remove = [
        r' block md:table-cell relative pl-\[40%\] md:pl-\d+ border-b border-gray-100 md:border-b-0 before:content-\[attr\(data-label\)\] before:absolute before:left-\d+ before:top-\d+( md:before:top-1/2)?( md:before:-translate-y-1/2)? before:text-\[11px\] before:font-bold before:uppercase before:text-gray-500 md:before:content-none text-right md:text-left',
        r' block md:table-cell relative pl-\[40%\] md:pl-\d+ before:content-\[attr\(data-label\)\] before:absolute before:left-\d+ before:top-\d+( md:before:top-1/2)?( md:before:-translate-y-1/2)? before:text-\[11px\] before:font-bold before:uppercase before:text-gray-500 md:before:content-none',
        r' block md:table-cell',
        r' block md:table-row absolute -top-full md:top-auto -left-full md:left-auto md:relative',
        r' block md:table-header-group',
        r' block md:table-row-group',
        r' block md:table-row border border-gray-200 md:border-0 mb-4 md:mb-0 rounded-xl md:rounded-none bg-white md:bg-transparent',
        r' block md:table',
        r' text-right md:text-left',
        r' md:justify-start',
        r' items-end md:items-start',
    ]
    
    for c in classes_to_remove:
        content = re.sub(c, '', content)

    # Clean up h-auto md:h-12 and py-4 md:py-0 etc.
    content = re.sub(r'h-auto md:h-(\d+)\s+', r'h-\1 ', content)
    content = re.sub(r'py-\d+ md:py-(\d+)\s+', r'py-\1 ', content)

    with open(filepath, 'w') as f:
        f.write(content)
    
    print(f"Cleaned {filepath}")

for f in glob.glob('resources/views/livewire/pages/receptionist/*.blade.php'):
    clean_file(f)
