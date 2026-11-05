from docx import Document
from pathlib import Path
import sys

for path in map(Path, sys.argv[1:]):
    doc = Document(path)
    out = path.with_suffix('.extracted.txt') if path.parent == Path('D:/Downloads') else Path('tmp') / (path.stem + '.extracted.txt')
    lines = [f'FILE {path}', f'PARAGRAPHS {len(doc.paragraphs)} TABLES {len(doc.tables)} SECTIONS {len(doc.sections)}']
    for i, p in enumerate(doc.paragraphs):
        s = p.text.strip()
        if s:
            lines.append(f'P{i} [{p.style.name}] {s}')
    for i, t in enumerate(doc.tables):
        lines.append(f'TABLE {i} {len(t.rows)}x{len(t.columns)}')
        for j, row in enumerate(t.rows[:25]):
            lines.append(f' R{j}: ' + ' | '.join(c.text.replace('\n', '; ') for c in row.cells))
    Path('tmp').mkdir(exist_ok=True)
    out = Path('tmp') / (path.stem + '.extracted.txt')
    out.write_text('\n'.join(lines), encoding='utf-8')
    print(out)
