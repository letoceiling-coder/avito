#!/bin/bash
# Скрипт для автоматической отправки изменений в git
# Использование: ./git-push.sh "Сообщение коммита"

# Цвета для вывода
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

echo -e "${CYAN}=== Отправка изменений в Git ===${NC}"

# Проверка наличия изменений
if [ -z "$(git status --porcelain)" ]; then
    echo -e "${YELLOW}Нет изменений для отправки.${NC}"
    exit 0
fi

# Сообщение коммита
if [ -z "$1" ]; then
    COMMIT_MSG="Update: $(date '+%Y-%m-%d %H:%M:%S')"
else
    COMMIT_MSG="$1"
fi

echo -e "\n${GREEN}Добавление файлов...${NC}"
git add .

if [ $? -ne 0 ]; then
    echo -e "${RED}Ошибка при добавлении файлов!${NC}"
    exit 1
fi

echo -e "${GREEN}Создание коммита с сообщением: $COMMIT_MSG${NC}"
git commit -m "$COMMIT_MSG"

if [ $? -ne 0 ]; then
    echo -e "${RED}Ошибка при создании коммита!${NC}"
    exit 1
fi

echo -e "${GREEN}Отправка в репозиторий...${NC}"
git push

if [ $? -ne 0 ]; then
    echo -e "${RED}Ошибка при отправке в репозиторий!${NC}"
    exit 1
fi

echo -e "\n${GREEN}=== Изменения успешно отправлены! ===${NC}"
