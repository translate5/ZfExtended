#DRAFT - ZfExtended REST API General Description

Each REST JSON Body send by RestControllers contains a "rows" container, and a "success" flag.

An optional errors array can also be supplied.

 {
   rows: mixed,
   success: true|false,
   errors: [optional array]
 }
 
###Errors: 
The errors array is optional and contains object of the form: 
 {
   'id': 'login',
   'type': 'error|notice|warning',
   'msg': 'Free text describing the error'
 }
 
Field "id": → optional, if omitted type error is assumed.
Field "type": → only needed for formulars and therefore PUT / POST requests to identify the field which produced the error.


## HTTP STATUS CODES

200 - as defined: all OK
Accompanied with a errors field, this means additional informations / notices.