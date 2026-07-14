import os
import re
import glob

def clean_file(filepath):
    with open(filepath, 'r') as f:
        content = f.read()

    # If file doesn't have our changes, skip
    if 'block md:table' not in content and 'md:table-cell' not in content:
        return

    # 1. Clean table tag
    content = re.sub(r' block md:table(?=["\'])', '', content)
    
    # 2. Clean thead
    content = re.sub(r' class="block md:table-header-group"', '', content)
    content = re.sub(r' block md:table-header-group(?=["\'])', '', content)
    
    # 3. Clean thead tr
    content = re.sub(r' block md:table-row absolute -top-full md:top-auto -left-full md:left-auto md:relative(?=["\'])', '', content)
    
    # 4. Clean th
    content = re.sub(r' block md:table-cell(?=["\'])', '', content)
    
    # 5. Clean tbody
    content = re.sub(r' block md:table-row-group(?=["\'])', '', content)
    
    # 6. Clean tbody tr
    content = re.sub(r' block md:table-row border border-gray-200 md:border-0 mb-4 md:mb-0 rounded-xl md:rounded-none bg-white md:bg-transparent(?=["\'])', '', content)
    
    # 7. Clean td
    # The td classes are very complex, e.g.:
    # class="h-auto md:h-12 px-6 py-4 md:py-4 font-mono text-xs font-semibold text-gray-400 block md:table-cell relative pl-[40%] md:pl-6 border-b border-gray-100 md:border-b-0 before:content-[attr(data-label)] before:absolute before:left-6 before:top-4 before:text-[11px] before:font-bold before:uppercase before:text-gray-500 md:before:content-none text-right md:text-left" data-label="#"
    
    # First, let's remove the data-label attributes
    content = re.sub(r' data-label="[^"]+"', '', content)
    
    # Now let's restore the td classes. It's tricky because there are different paddings.
    # We can match the entire added block:
    # "block md:table-cell relative pl-[40%] md:pl-... border-b border-gray-100 md:border-b-0 before:content-[attr(data-label)] before:absolute before:left-... before:top-... md:before:top-1/2 md:before:-translate-y-1/2 before:text-[11px] before:font-bold before:uppercase before:text-gray-500 md:before:content-none text-right md:text-left"
    
    content = re.sub(r' block md:table-cell relative pl-\[40%\] md:pl-\d+ border-b border-gray-100 md:border-b-0 before:content-\[attr\(data-label\)\] before:absolute before:left-\d+ before:top-4( md:before:top-1/2)?( md:before:-translate-y-1/2)? before:text-\[11px\] before:font-bold before:uppercase before:text-gray-500 md:before:content-none text-right md:text-left', '', content)
    content = re.sub(r' block md:table-cell relative pl-\[40%\] md:pl-\d+ border-b border-gray-100 md:border-b-0 before:content-\[attr\(data-label\)\] before:absolute before:left-\d+ before:top-4 before:text-\[11px\] before:font-bold before:uppercase before:text-gray-500 md:before:content-none text-right md:text-left', '', content)

    # Clean up h-auto md:h-12 and py-4 md:py-4 etc.
    content = re.sub(r'h-auto md:h-(\d+)\s+', r'h-\1 ', content)
    content = re.sub(r'py-4 md:py-(\d+)\s+', r'py-\1 ', content)

    with open(filepath, 'w') as f:
        f.write(content)
    
    print(f"Cleaned {filepath}")

for f in glob.glob('resources/views/livewire/pages/receptionist/*.blade.php'):
    clean_file(f)
