from PyPDF2 import PdfReader, PdfWriter
from PyPDF2.generic import NameObject
import mysql.connector as mysql

reader = PdfReader("baseOriginal.pdf")


page = reader.pages[0]
fields = reader.get_fields()

db =  mysql.connect(
  host ="localhost",
  user ="userdp",
  passwd ="385402292Mica_02",
  database = "vtgsa_ventas"
)

cursor = db.cursor()
query = "SELECT N.id_lista, N.documento, N.nombres, N.ciudad, N.telefono, N.correo, N.ruc, N.cedula, N.razonSocial, N.actividadContribuyente, N.fechaInicioActividades, E.nombreComercial, E.direccionCompleta FROM notas_registros N LEFT JOIN notas_registros_establecimientos E ON N.id_lista = E.id_lista WHERE (N.banco=28) AND (N.identificador='2023-02-09-1') AND (E.tipoEstablecimiento='MAT') ORDER BY N.id_lista LIMIT 1"
 
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
    cursor2.execute("SELECT contacto FROM notas_registros_contactos  WHERE (id_lista="+str(id_lista)+") AND (LENGTH(contacto)=9) ORDER BY id_contacto ASC LIMIT 1") 
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
        writer.pages[0], {"PAIS CIUDAD": "ECUADOR"},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"CIUDAD": ciudad},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"RUC": ruc},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"FECHA CONSTITUCION": record[10]},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"RAZON SOCIAL": razonSocial},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"Text12ACTIVIDAD ECONOMICA 01": actividad1},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"ADTIVIDAD ECONO0MICA 02": actividad2},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"ACTIVIDAD ECONOMICA 03": actividad3},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"TELEFONO 1": convencional},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"TELEFONO 2": celular},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"CIUDADA PROVINCIA": provincia},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"CANTON": canton},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"PARROQUIA": parroquia},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"BARRIO": "X"},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"CALLE PRINCIPAL": direccionCompleta},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"NUMERO": "X"},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"TRANSVERSAL": "X"},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"EDIFICIO": "X"},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"PISO": "X"},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"DEPARTAMENTO": "X"},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"ACTIVOS": "X"},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"PASIVOS": "X"},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"PATRIMONIO": "X"},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"INGRESOS": "X"},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"GASTOS": "X"},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"FUENTE DE GENERACION DE RECURSOS": "X"},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"REFERENCIA": "X"},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"NOMBRE Y APELLIDO": nombresApellidos},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"NO": cedula},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"CELULAR": celular},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"PROVEEDOR 1": "X"},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"COMPRAS": "X"},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"PLAZO": "X"},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"PERSONA": "X"},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"TELEFONO DE CONTACTO": "X"},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"DRODUCTO 2": "X"},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"COMPRAS 02": "X"},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"PLAZO 02": "X"},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"PERSONA 02": "X"},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"TELEFONO CONTACTO 02": "X"},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"NOMBRES Y APELLIDOS": "X"},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"CEDULA": "X"},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"CEDULA02": "X"},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"CORREO ELECTRONICO": correo},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"PAIS": "ECUADOR"},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"nombre web": nombresApellidos},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"cargo web": propietario},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"cedula web": cedula},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"numero celular web": celular},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"NOMBRE TARJETA 1": nombresApellidos},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"CELULAR 1": celular},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"ADM 01": razonSocial},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"ADM 2": "MILES 150"},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"ADM3": razonSocial},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"ADM 4": propietario},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"ADM 5": cedula},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"ADM 6": correo},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"ADM7": convencional},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"ADM 8": celular},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"NACIONALIDAD 00012243": "ECUADOR"},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"NACIONALIDAD RWRTRT": "ECUADOR"},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"CORREO ELECTRONICO 34R7": "X"},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"CORREO ER6YFJH": correo},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"correo 3463576N": correo},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"PAIS 35657": "X"},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"NO 245": "X"},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"BANCO  ETY": "x"},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"CARGO 4566": "X"},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"ID V4542": cedula},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"UTILIDAD 24524GF": "X"},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"LUGAR 0394": "QUITO"},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"FECHA 024856": "15/12/2022"},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"Cargo 1": propietario},
    )
    writer.update_page_form_field_values(
        writer.pages[0], {"PROPOSITO 01": "ORGANIZAR GASTOS DE EMPRESA"},
    )

    carpeta = "/var/www/html/digitalpayment_api/public/tmp/formularios"

    nombreFormulario = "Formulario-"+str(documento)+".pdf"
    
    cursorAct = db.cursor()
    query = "UPDATE notas_registros SET formulario='"+str(nombreFormulario)+"' WHERE id_lista="+str(id_lista)
    cursorAct.execute(query)
    db.commit()

    # write "output" to PyPDF2-output.pdf
    with open(carpeta+"/"+nombreFormulario, "wb") as output_stream:
        writer.write(output_stream)

    print(record[1])

db.close()