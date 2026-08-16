import type { TranslationMessages } from 'react-admin';
import polyglotI18nProvider from 'ra-i18n-polyglot';

const mensajes: TranslationMessages = {
  ra: {
    action: {
      add_filter: 'Añadir filtro', add: 'Añadir', back: 'Volver', bulk_actions: '1 elemento seleccionado |||| %{smart_count} elementos seleccionados',
      cancel: 'Cancelar', clear_array_input: 'Vaciar la lista', clear_input_value: 'Borrar valor', clone: 'Duplicar', confirm: 'Confirmar',
      create: 'Crear', create_item: 'Crear %{item}', delete: 'Eliminar', edit: 'Editar', export: 'Exportar', list: 'Lista', refresh: 'Actualizar',
      remove_filter: 'Quitar este filtro', remove_all_filters: 'Quitar todos los filtros', remove: 'Eliminar', reset: 'Restablecer', save: 'Guardar',
      search: 'Buscar', search_columns: 'Buscar columnas', select_all: 'Seleccionar todo', select_all_button: 'Seleccionar todo', select_row: 'Seleccionar esta fila',
      show: 'Ver', sort: 'Ordenar', undo: 'Deshacer', unselect: 'Deseleccionar', expand: 'Expandir', close: 'Cerrar', open_menu: 'Abrir menú',
      close_menu: 'Cerrar menú', update: 'Actualizar', move_up: 'Subir', move_down: 'Bajar', open: 'Abrir', toggle_theme: 'Cambiar tema claro u oscuro',
      select_columns: 'Columnas', update_application: 'Recargar aplicación',
    },
    boolean: { true: 'Sí', false: 'No', null: ' ' },
    page: {
      create: 'Crear %{name}', dashboard: 'Inicio', edit: '%{name} %{recordRepresentation}', error: 'No se pudo mostrar esta página', list: '%{name}',
      loading: 'Cargando', not_found: 'Página no encontrada', show: '%{name} %{recordRepresentation}', empty: 'Todavía no hay %{name}.',
      invite: '¿Quieres añadir uno?', access_denied: 'Acceso denegado', authentication_error: 'Error de autenticación',
    },
    input: {
      file: { upload_several: 'Arrastra archivos aquí o pulsa para seleccionarlos.', upload_single: 'Arrastra un archivo aquí o pulsa para seleccionarlo.' },
      image: { upload_several: 'Arrastra imágenes aquí o pulsa para seleccionarlas.', upload_single: 'Arrastra una imagen aquí o pulsa para seleccionarla.' },
      references: { all_missing: 'No se pudieron encontrar los datos relacionados.', many_missing: 'Alguno de los elementos relacionados ya no está disponible.', single_missing: 'El elemento relacionado ya no está disponible.' },
      password: { toggle_visible: 'Ocultar contraseña', toggle_hidden: 'Mostrar contraseña' },
    },
    message: {
      about: 'Acerca de', access_denied: 'No tienes permisos para acceder a esta página', are_you_sure: '¿Estás seguro?',
      authentication_error: 'El servidor de autenticación devolvió un error y no se pudieron comprobar tus credenciales.',
      auth_error: 'Se produjo un error al validar la sesión.',
      bulk_delete_content: '¿Quieres eliminar este elemento? |||| ¿Quieres eliminar estos %{smart_count} elementos?',
      bulk_delete_title: 'Eliminar %{name} |||| Eliminar %{smart_count} elementos',
      bulk_update_content: '¿Quieres actualizar %{name} %{recordRepresentation}? |||| ¿Quieres actualizar estos %{smart_count} elementos?',
      bulk_update_title: 'Actualizar %{name} %{recordRepresentation} |||| Actualizar %{smart_count} elementos',
      clear_array_input: '¿Quieres vaciar toda la lista?', delete_content: '¿Quieres eliminar este elemento?', delete_title: 'Eliminar %{name} %{recordRepresentation}',
      details: 'Detalles', error: 'Se produjo un error en el panel y no se pudo completar la solicitud.',
      invalid_form: 'El formulario contiene errores. Revisa los campos indicados.', loading: 'Espera un momento', no: 'No',
      not_found: 'La dirección no existe o el enlace ya no es válido.',
      select_all_limit_reached: 'Hay demasiados elementos. Solo se seleccionaron los primeros %{max}.',
      unsaved_changes: 'Hay cambios sin guardar. ¿Quieres salir y descartarlos?', yes: 'Sí',
      placeholder_data_warning: 'No se pudieron actualizar los datos por un problema de red.',
    },
    navigation: {
      clear_filters: 'Limpiar filtros', no_filtered_results: 'No se encontraron %{name} con estos filtros.', no_results: 'No se encontraron %{name}',
      no_more_results: 'La página %{page} está fuera del intervalo. Vuelve a la página anterior.', page_out_of_boundaries: 'La página %{page} está fuera del intervalo',
      page_out_from_end: 'No se puede avanzar más allá de la última página', page_out_from_begin: 'No se puede retroceder antes de la primera página',
      page_range_info: '%{offsetBegin}-%{offsetEnd} de %{total}', partial_page_range_info: '%{offsetBegin}-%{offsetEnd} de más de %{offsetEnd}',
      current_page: 'Página %{page}', page: 'Ir a la página %{page}', first: 'Ir a la primera página', last: 'Ir a la última página',
      next: 'Ir a la página siguiente', previous: 'Ir a la página anterior', page_rows_per_page: 'Filas por página:', skip_nav: 'Saltar al contenido',
    },
    sort: { sort_by: 'Ordenar por %{field_lower_first} %{order}', ASC: 'ascendente', DESC: 'descendente' },
    auth: {
      auth_check_error: 'Inicia sesión para continuar', user_menu: 'Perfil', username: 'Usuario', password: 'Contraseña', email: 'Correo electrónico',
      sign_in: 'Entrar', sign_in_error: 'No se pudo iniciar sesión. Inténtalo de nuevo.', logout: 'Cerrar sesión',
    },
    notification: {
      updated: 'Elemento actualizado |||| %{smart_count} elementos actualizados', created: 'Elemento creado', deleted: 'Elemento eliminado |||| %{smart_count} elementos eliminados',
      bad_item: 'Elemento incorrecto', item_doesnt_exist: 'El elemento ya no existe', http_error: 'No se pudo comunicar con el servidor',
      data_provider_error: 'Se produjo un error al obtener los datos.', i18n_error: 'No se pudieron cargar las traducciones', canceled: 'Acción cancelada',
      logged_out: 'Tu sesión ha terminado. Vuelve a entrar para continuar.', not_authorized: 'No tienes autorización para acceder a este recurso.',
      application_update_available: 'Hay una nueva versión disponible.', offline: 'Sin conexión. No se pudieron obtener los datos.',
    },
    validation: {
      required: 'Campo obligatorio', minLength: 'Debe tener al menos %{min} caracteres', maxLength: 'Debe tener %{max} caracteres o menos',
      minValue: 'Debe ser como mínimo %{min}', maxValue: 'Debe ser como máximo %{max}', number: 'Debe ser un número',
      email: 'Introduce un correo electrónico válido', oneOf: 'Debe ser una de estas opciones: %{options}',
      regex: 'No tiene el formato esperado: %{pattern}', unique: 'Este valor ya existe',
    },
    saved_queries: {
      label: 'Consultas guardadas', query_name: 'Nombre de la consulta', new_label: 'Guardar consulta actual…', new_dialog_title: 'Guardar consulta como',
      remove_label: 'Eliminar consulta guardada', remove_label_with_name: 'Eliminar consulta «%{name}»', remove_dialog_title: '¿Eliminar la consulta guardada?',
      remove_message: '¿Quieres eliminar esta consulta de la lista?', help: 'Aplica filtros y guarda la consulta para usarla después',
    },
    guesser: { empty: { title: 'No hay datos que mostrar', message: 'Revisa la conexión con la API' } },
    configurable: {
      customize: 'Personalizar', configureMode: 'Configurar esta página',
      inspector: { title: 'Inspector', content: 'Pasa el cursor sobre los elementos para configurarlos', reset: 'Restablecer ajustes', hideAll: 'Ocultar todo', showAll: 'Mostrar todo' },
      Datagrid: { title: 'Tabla', unlabeled: 'Columna sin etiqueta n.º %{column}' },
      SimpleForm: { title: 'Formulario', unlabeled: 'Campo sin etiqueta n.º %{input}' },
      SimpleList: { title: 'Lista', primaryText: 'Texto principal', secondaryText: 'Texto secundario', tertiaryText: 'Texto adicional' },
    },
  },
  resources: {
    recetas: { name: 'Receta |||| Recetas', fields: { id: 'ID', titulo: 'Título', imagen_url: 'Imagen', creado_en: 'Publicada' } },
  },
};

export const i18nProvider = polyglotI18nProvider(() => mensajes, 'es', [
  { locale: 'es', name: 'Español' },
]);
