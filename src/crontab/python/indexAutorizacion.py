from PyPDF2 import PdfReader, PdfWriter
from PyPDF2.generic import NameObject
import mysql.connector as mysql

reader = PdfReader("autorizacionOriginal.pdf")


page = reader.pages[0]
fields = reader.get_fields()

db =  mysql.connect(
  host ="localhost",
  user ="userdp",
  passwd ="385402292Mica_02",
  database = "vtgsa_ventas"
)

cursor = db.cursor()
query = "SELECT N.id_lista, N.documento, N.nombres, N.ciudad, N.telefono, N.correo, N.ruc, N.cedula, N.razonSocial, N.actividadContribuyente, N.fechaInicioActividades, E.nombreComercial, E.direccionCompleta FROM notas_registros N LEFT JOIN notas_registros_establecimientos E ON N.id_lista = E.id_lista WHERE (N.banco=25) AND (N.identificador='2022-12-12') AND (E.tipoEstablecimiento='MAT') ORDER BY N.id_lista"
 
cursor.execute(query) 
records = cursor.fetchall() 
for record in records:
    documento = record[1]
    id_lista = record[0]
    nombres = record[2]
    ciudad = record[3]
    ruc = record[6]
    cedula = record[7]
    razonSocial = record[8]
    celular = record[4]
    correo = record[5]

    convencional = celular
    cursor2 = db.cursor()
    cursor2.execute("SELECT contacto FROM notas_registros_contactos  WHERE (id_lista=212061) AND (LENGTH(contacto)=9) ORDER BY id_contacto ASC LIMIT 1") 
    listaContactos = cursor2.fetchall() 
    for contacto in listaContactos:
        convencional = contacto[0]

    separaNombres = razonSocial.split()
    if (len(separaNombres) == 4):
        nombresApellidos = separaNombres[2]+" "+separaNombres[3]+" "+separaNombres[0]+" "+separaNombres[1]
    elif (len(separaNombres) == 3):
        nombresApellidos = separaNombres[1]+" "+separaNombres[2]+" "+separaNombres[0]
    elif (len(separaNombres) == 2):
        nombresApellidos = separaNombres[1]+" "+separaNombres[0]

    actividad = record[9]
    actividad1 = actividad[0:65]
    actividad2 = actividad[66:130]
    actividad3 = actividad[131:len(actividad)]

    direccion = record[12]
    separa = direccion.split("/")
    provincia = separa[0]
    canton = separa[1]
    parroquia = separa[2]
    direccionCompleta = separa[3]

    propietario = "PROPIETARIO"

    writer = PdfWriter()
   
    writer.add_page(page)

    writer.update_page_form_field_values(
        writer.pages[0], {"cliente": razonSocial},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"establecimiento": razonSocial},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"fecha": "2022-12-19"},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"cliente": nombresApellidos},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"cargo": propietario},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"razon social": razonSocial},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"RUC": ruc},
    )

    carpeta = "/var/www/html/digitalpayment_api/public/tmp/formularios"

    nombreFormulario = "Autorizacion-"+str(documento)+".pdf"
    
    cursorAct = db.cursor()
    query = "UPDATE notas_registros SET formulario='"+str(nombreFormulario)+"' WHERE id_lista="+str(id_lista)
    cursorAct.execute(query)
    db.commit()

    # write "output" to PyPDF2-output.pdf
    with open(carpeta+"/"+nombreFormulario, "wb") as output_stream:
        writer.write(output_stream)

    print(record[1])

db.close()