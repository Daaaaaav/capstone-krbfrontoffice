import glob
import re

files = glob.glob('resources/views/livewire/pages/receptionist/*.blade.php')

for filepath in files:
    with open(filepath, 'r') as f:
        content = f.read()
        
    # Replace >#{{ $rowNo }}< with >{{ $rowNo }}<
    content = re.sub(r'>#\{\{\s*\$rowNo\s*\}\}<', '>{{ $rowNo }}<', content)
    
    # Replace >#{{ $<var>-><prop> }}< with >{{ $loop->iteration }}<
    content = re.sub(r'>#\{\{\s*\$[a-zA-Z0-9_]+->[a-zA-Z0-9_]+\s*\}\}<', '>{{ $loop->iteration }}<', content)
    
    # Replace >#{{ $id }}< with >{{ $loop->iteration }}<
    content = re.sub(r'>#\{\{\s*\$id\s*\}\}<', '>{{ $loop->iteration }}<', content)
    
    # Also replace bare #{{ $id }} outside of tags if it's in a td
    # E.g. in room-approval it's formatted like:
    # <td class="...">
    #     #{{ $id }}
    # </td>
    content = re.sub(r'>\s*#\{\{\s*\$[a-zA-Z0-9_]+->[a-zA-Z0-9_]+\s*\}\}\s*<', '>\n                                                        {{ $loop->iteration }}\n                                                    <', content)
    content = re.sub(r'>\s*#\{\{\s*\$id\s*\}\}\s*<', '>\n                                                        {{ $loop->iteration }}\n                                                    <', content)
    
    with open(filepath, 'w') as f:
        f.write(content)
        
    print(f"Fixed {filepath}")
