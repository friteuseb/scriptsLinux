#!/bin/bash

TASKS_FILE=~/tasks.md

# Fonction pour ajouter une tâche
add_task() {
    echo "- [ ] $*" >> $TASKS_FILE
    echo "Tâche ajoutée : $*"
}

# Fonction pour marquer une tâche comme terminée
complete_task() {
    sed -i "${1}s/\[ \]/[x]/" $TASKS_FILE
    echo "Tâche #$1 marquée comme terminée"
}

# Fonction pour lister les tâches
list_tasks() {
    echo "Tâches à faire :"
    grep -n "^- \[ \]" $TASKS_FILE | sed 's/^/  /'
}

# Menu principal
case "$1" in
    add)
        shift
        add_task "$*"
        ;;
    complete)
        complete_task $2
        ;;
    list)
        list_tasks
        ;;
    *)
        echo "Usage: task {add <tâche>|complete <numéro>|list}"
        exit 1
esac

exit 0
