from PyPDF2 import PdfReader, PdfWriter
from PyPDF2.generic import NameObject

reader = PdfReader("baseOriginal.pdf")
writer = PdfWriter()

page = reader.pages[0]
fields = reader.get_fields()



c=0
for field in fields:
    print(field)
    # if c==0:
    #     print(NameObject(field))
    # c=+1


    



# writer.add_page(page)

# for field in fields:
#     writer.update_page_form_field_values(
#         writer.pages[0], {field: "CARLOS MINO"},
#     )
# # writer.update_page_form_field_values(
# #     writer.pages[0], {"CIUDAD": "QUITOFS"},
# # )

# # write "output" to PyPDF2-output.pdf
# with open("filled-out.pdf", "wb") as output_stream:
#     writer.write(output_stream)







# writer.add_page(page)
# writer.update_page_form_field_values(
#     writer.pages[0], {"PROPOSITO 03": "LLENADO AUTOMATICO"},
# )
# with open("filled-out-12.pdf", "wb") as output_stream:
#     writer.write(output_stream)