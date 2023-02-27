import os
from PyPDF2 import PdfMerger
import datetime
pdfs = os.listdir(r'/')
today = datetime.date.today()
# os.listdir will create the list of all files in a directory
merger = PdfMerger(strict=False)

# merger is used for merging multiple files into one and merger.append(absfile) will append 
# the files one by one until all pdfs are appended in the result file.
final_filename = ''

for file in pdfs:
  # Open files
  if file.endswith(".pdf"):
    final_filename += file.split('.')[-2]
    path_with_file = os.path.join(r'C:\Desktop\Work', file)
    input = open(path_with_file, 'rb')
    print(path_with_file)
    print(input.seek(0, os.SEEK_END))
    merger.append(input, import_bookmarks=False)

print(final_filename)

merger.write(f'{final_filename}.pdf')
merger.close()